<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\BusDocument;
use App\Entity\BusMaintenance;
use App\Entity\CashSessionBalance;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\FinancialOperation;
use App\Entity\RouteStop;
use App\Entity\Trip;
use App\Entity\TripSeat;
use App\Entity\User;
use App\Enum\CashSessionStatus;
use App\Enum\FlowType;
use App\Enum\MaintenanceStatus;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use App\Enum\TripStatus;
use App\Repository\ExchangeRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Données du tableau de bord d'une agence, lues dans la base.
 *
 * Périmètre : le siège (utilisateur sans succursale, ou rattaché à la succursale « SIEGE ») voit toute l'agence
 * et peut observer une succursale ; une succursale ne voit que ses propres flux.
 * Les bus et les documents appartiennent à l'agence (pas à une succursale) : leurs alertes sont les mêmes partout.
 */
final class AgencyDashboardService
{
    private const UPCOMING_LIMIT = 8;
    private const DOCUMENT_ALERT_DAYS = 30;
    private const SELLABLE_SEAT_TYPES = [SeatType::Normal, SeatType::Vip, SeatType::Extra];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeRateRepository $rates,
    ) {
    }

    /**
     * Variables attendues par admin/agency/dashboard/index.html.twig.
     *
     * @return array<string, mixed>
     */
    public function build(User $user, ?string $branchCode, \DateTimeImmutable $now): array
    {
        $agency = $user->getAgency() ?? throw new \LogicException('Cet utilisateur n\'est rattaché à aucune agence.');
        $userBranch = $user->getBranch();
        $isCentral = null === $userBranch || AgencyRegistrationService::HEADQUARTERS_CODE === $userBranch->getCode();

        $branches = $this->branches($agency);
        $selected = null;

        if ($isCentral && null !== $branchCode && '' !== $branchCode) {
            foreach ($branches as $branch) {
                if ($branch->getCode() === $branchCode) {
                    $selected = $branch;
                    break;
                }
            }
        }

        // Succursale observée ; null = toute l'agence.
        $scope = $isCentral ? $selected : $userBranch;
        $today = $now->setTime(0, 0);

        $currency = $this->currencySettings($agency, $now);
        $todayTripIds = $this->tripIds($agency, $scope, $today, $today, [TripStatus::Scheduled, TripStatus::Boarding, TripStatus::Departed, TripStatus::Arrived]);
        $todaySeats = $this->seatCounts($todayTripIds, $now);

        return [
            'isCentral' => $isCentral,
            'current_branch_name' => $isCentral ? ($selected?->getName() ?? 'Siège Central') : (string) $userBranch?->getName(),
            'selected_branch' => $selected?->getCode() ?? '',
            'branches_list' => array_map(
                static fn (Branch $b): array => ['code' => (string) $b->getCode(), 'name' => (string) $b->getName()],
                $branches,
            ),
            'stats' => [
                'revenue' => $this->cashOnHand($agency, $scope, $currency['base'], $now),
                'active_trips' => \count($todayTripIds),
                'booked_seats' => array_sum(array_column($todaySeats, 'taken')),
                'total_seats' => array_sum(array_column($todaySeats, 'capacity')),
                'broken_buses' => $this->brokenBuses($agency),
                'expired_docs' => $this->documentsToRenew($agency, $today),
            ],
            'upcoming_trips' => $this->upcomingTrips($agency, $scope, $now),
            'agency_currency_settings' => $currency['settings'],
        ];
    }

    // ------------------------------------------------------------------ succursales

    /**
     * @return list<Branch>
     */
    private function branches(Agency $agency): array
    {
        /** @var list<Branch> $branches */
        $branches = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Branch::class, 'b')
            ->andWhere('b.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $branches;
    }

    // ------------------------------------------------------------------ voyages

    /**
     * Voyages de l'agence sur une période. Avec une succursale : ceux dont la route dessert la ville de cette succursale.
     *
     * @param list<TripStatus> $statuses
     */
    private function scopedTrips(Agency $agency, ?Branch $branch, \DateTimeImmutable $from, \DateTimeImmutable $to, array $statuses): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->join('t.bus', 'b')
            ->andWhere('r.agency = :agency')
            ->andWhere('t.departureDate >= :from')
            ->andWhere('t.departureDate <= :to')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->setParameter('statuses', array_map(static fn (TripStatus $s): string => $s->value, $statuses));

        if (null !== $branch) {
            $qb->andWhere(sprintf(
                'EXISTS (SELECT rs.id FROM %s rs WHERE rs.route = r AND rs.isActive = :yes AND LOWER(rs.city) = :city)',
                RouteStop::class,
            ))
                ->setParameter('yes', true)
                ->setParameter('city', mb_strtolower(trim((string) $branch->getCity())));
        }

        return $qb;
    }

    /**
     * @param list<TripStatus> $statuses
     *
     * @return list<int>
     */
    private function tripIds(Agency $agency, ?Branch $branch, \DateTimeImmutable $from, \DateTimeImmutable $to, array $statuses): array
    {
        $rows = $this->scopedTrips($agency, $branch, $from, $to, $statuses)
            ->select('t.id AS id')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Places vendables par voyage : capacité et places prises (réservées, ou retenues dont le délai court encore).
     *
     * @param list<int> $tripIds
     *
     * @return array<int, array{capacity: int, taken: int}>
     */
    private function seatCounts(array $tripIds, \DateTimeImmutable $now): array
    {
        if ([] === $tripIds) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select(
                'IDENTITY(ts.trip) AS tripId',
                'COUNT(ts.id) AS capacity',
                'SUM(CASE WHEN ts.seatStatus = :reserved THEN 1 WHEN (ts.seatStatus = :held AND ts.heldUntil >= :now) THEN 1 ELSE 0 END) AS taken',
            )
            ->from(TripSeat::class, 'ts')
            ->join('ts.seat', 's')
            ->andWhere('IDENTITY(ts.trip) IN (:tripIds)')
            ->andWhere('s.type IN (:types)')
            ->setParameter('tripIds', $tripIds)
            ->setParameter('types', array_map(static fn (SeatType $t): string => $t->value, self::SELLABLE_SEAT_TYPES))
            ->setParameter('reserved', TripSeatStatus::Reserved->value)
            ->setParameter('held', TripSeatStatus::Held->value)
            ->setParameter('now', $now)
            ->groupBy('ts.trip')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['tripId']] = ['capacity' => (int) $row['capacity'], 'taken' => (int) $row['taken']];
        }

        return $counts;
    }

    /**
     * Prochains départs : voyages programmés ou en embarquement, aujourd'hui et demain.
     *
     * @return list<array{time: string, bus: string, plate: string, route: string, seats_taken: int, capacity: int, status: string, statusLabel: string}>
     */
    private function upcomingTrips(Agency $agency, ?Branch $branch, \DateTimeImmutable $now): array
    {
        $today = $now->setTime(0, 0);

        /** @var list<Trip> $trips */
        $trips = $this->scopedTrips($agency, $branch, $today, $today->modify('+1 day'), [TripStatus::Scheduled, TripStatus::Boarding])
            ->select('t', 'r', 'b')
            ->orderBy('t.departureDate', 'ASC')
            ->addOrderBy('t.departureTime', 'ASC')
            ->setMaxResults(40)
            ->getQuery()
            ->getResult();

        $candidates = [];

        foreach ($trips as $trip) {
            $date = $trip->getDepartureDate();
            $time = $trip->getDepartureTime();

            if (null === $date || null === $time) {
                continue;
            }

            $at = \DateTimeImmutable::createFromInterface($date)->setTime((int) $time->format('H'), (int) $time->format('i'));

            // Un bus en embarquement reste affiché même si l'heure prévue est passée.
            if (TripStatus::Boarding !== $trip->getStatus() && $at < $now) {
                continue;
            }

            $candidates[] = [$at, $trip];
        }

        usort($candidates, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $candidates = \array_slice($candidates, 0, self::UPCOMING_LIMIT);

        $seats = $this->seatCounts(
            array_map(static fn (array $c): int => (int) $c[1]->getId(), $candidates),
            $now,
        );

        $out = [];

        foreach ($candidates as [$at, $trip]) {
            $bus = $trip->getBus();
            $boarding = TripStatus::Boarding === $trip->getStatus();
            $soon = $at <= $now->modify('+60 minutes');

            $out[] = [
                'time' => $at->format('Y-m-d') === $now->format('Y-m-d') ? $at->format('H:i') : 'Demain '.$at->format('H:i'),
                'bus' => trim($bus?->getBrand().' '.$bus?->getModel()),
                'plate' => (string) $bus?->getPlateNumber(),
                'route' => (string) $trip->getRoute()?->getName(),
                'seats_taken' => $seats[(int) $trip->getId()]['taken'] ?? 0,
                'capacity' => $seats[(int) $trip->getId()]['capacity'] ?? 0,
                'status' => $boarding ? 'loading' : 'ready',
                'statusLabel' => $boarding ? 'En Embarquement' : ($soon ? 'Prêt au départ' : 'Programmé'),
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------ alertes

    /**
     * Nombre de bus de l'agence immobilisés : au moins une panne ouverte (statut « broken »).
     */
    private function brokenBuses(Agency $agency): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT b.id)')
            ->from(BusMaintenance::class, 'm')
            ->join('m.bus', 'b')
            ->andWhere('b.agency = :agency')
            ->andWhere('m.status = :broken')
            ->setParameter('agency', $agency)
            ->setParameter('broken', MaintenanceStatus::Broken->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Documents de bus expirés ou qui expirent dans les prochains jours.
     */
    private function documentsToRenew(Agency $agency, \DateTimeImmutable $today): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(BusDocument::class, 'd')
            ->join('d.bus', 'b')
            ->andWhere('b.agency = :agency')
            ->andWhere('d.expiredAt <= :limit')
            ->setParameter('agency', $agency)
            ->setParameter('limit', $today->modify(sprintf('+%d days', self::DOCUMENT_ALERT_DAYS)), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ------------------------------------------------------------------ caisse et devises

    /**
     * Espèces en caisse dans les sessions ouvertes, converties dans la devise de base de l'agence :
     * solde d'ouverture déclaré + entrées - sorties de chaque session, par devise.
     *
     * @return array{amount: float, currency: string, missing_rates: list<string>}
     */
    private function cashOnHand(Agency $agency, ?Branch $branch, ?Currency $base, \DateTimeImmutable $now): array
    {
        /** @var array<int, array{currency: Currency, amount: float}> $byCurrency */
        $byCurrency = [];

        $qb = $this->em->createQueryBuilder()
            ->select('cb', 'c')
            ->from(CashSessionBalance::class, 'cb')
            ->join('cb.currency', 'c')
            ->join('cb.cashSession', 'cs')
            ->join('cs.cashRegister', 'cr')
            ->andWhere('cr.agency = :agency')
            ->andWhere('cs.status = :opened')
            ->setParameter('agency', $agency)
            ->setParameter('opened', CashSessionStatus::Opened->value);

        if (null !== $branch) {
            $qb->andWhere('cr.branch = :branch')->setParameter('branch', $branch);
        }

        /** @var list<CashSessionBalance> $balances */
        $balances = $qb->getQuery()->getResult();

        foreach ($balances as $balance) {
            $currency = $balance->getCurrency();
            $id = (int) $currency?->getId();

            if (null === $currency) {
                continue;
            }

            $byCurrency[$id] ??= ['currency' => $currency, 'amount' => 0.0];
            $byCurrency[$id]['amount'] += (float) $balance->getOpeningBalanceDeclared();
        }

        $qb = $this->em->createQueryBuilder()
            ->select('IDENTITY(fo.currency) AS currencyId', 'fo.type AS type', 'SUM(fo.amount) AS total')
            ->from(FinancialOperation::class, 'fo')
            ->join('fo.cashSession', 'cs')
            ->join('cs.cashRegister', 'cr')
            ->andWhere('cr.agency = :agency')
            ->andWhere('cs.status = :opened')
            ->setParameter('agency', $agency)
            ->setParameter('opened', CashSessionStatus::Opened->value)
            ->groupBy('fo.currency')
            ->addGroupBy('fo.type');

        if (null !== $branch) {
            $qb->andWhere('cr.branch = :branch')->setParameter('branch', $branch);
        }

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $id = (int) $row['currencyId'];
            $type = $row['type'] instanceof \BackedEnum ? $row['type']->value : (string) $row['type'];

            $byCurrency[$id] ??= ['currency' => $this->em->getReference(Currency::class, $id), 'amount' => 0.0];
            $byCurrency[$id]['amount'] += FlowType::In->value === $type ? (float) $row['total'] : -(float) $row['total'];
        }

        $total = 0.0;
        $missing = [];

        foreach ($byCurrency as $entry) {
            $converted = null === $base ? null : $this->convert($entry['amount'], $entry['currency'], $base, $agency, $now);

            if (null === $converted) {
                $missing[] = (string) $entry['currency']->getCode();
                continue;
            }

            $total += $converted;
        }

        return [
            'amount' => round($total, 2),
            'currency' => (string) $base?->getCode(),
            'missing_rates' => $missing,
        ];
    }

    /**
     * Montant converti vers la devise de base, au dernier taux connu (taux de l'agence d'abord, sinon taux global).
     * Null si aucun taux n'existe entre les deux devises.
     */
    private function convert(float $amount, Currency $from, Currency $base, Agency $agency, \DateTimeImmutable $now): ?float
    {
        if ($from->getId() === $base->getId()) {
            return $amount;
        }

        $direct = $this->rates->findLatest($from, $base, $agency, $now);
        if (null !== $direct) {
            return $amount * (float) $direct->getRate();
        }

        $inverse = $this->rates->findLatest($base, $from, $agency, $now);
        if (null !== $inverse && (float) $inverse->getRate() > 0.0) {
            return $amount / (float) $inverse->getRate();
        }

        return null;
    }

    /**
     * Devise de base de l'agence et devises acceptées aux guichets, avec leur taux « 1 devise = X devise de base ».
     *
     * @return array{base: ?Currency, settings: array{base_currency: string, accepted_currencies: list<array{code: string, name: string, exchange_rate: ?float, isActive: bool}>}}
     */
    private function currencySettings(Agency $agency, \DateTimeImmutable $now): array
    {
        /** @var list<CurrencyAgency> $links */
        $links = $this->em->createQueryBuilder()
            ->select('ca', 'c')
            ->from(CurrencyAgency::class, 'ca')
            ->join('ca.currency', 'c')
            ->andWhere('ca.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();

        $base = null;

        foreach ($links as $link) {
            if ($link->isBase()) {
                $base = $link->getCurrency();
                break;
            }
        }

        $base ??= $agency->getDefaultCurrency();

        $accepted = [];

        foreach ($links as $link) {
            $currency = $link->getCurrency();

            if (null === $currency || (null !== $base && $currency->getId() === $base->getId())) {
                continue;
            }

            $accepted[] = [
                'code' => (string) $currency->getCode(),
                'name' => (string) $currency->getName(),
                'exchange_rate' => null === $base ? null : $this->convert(1.0, $currency, $base, $agency, $now),
                'isActive' => $link->isActive(),
            ];
        }

        return [
            'base' => $base,
            'settings' => [
                'base_currency' => (string) $base?->getCode(),
                'accepted_currencies' => $accepted,
            ],
        ];
    }
}
