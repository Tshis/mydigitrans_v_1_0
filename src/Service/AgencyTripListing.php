<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\TripStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Liste des voyages d'une agence, ses statistiques et ses filtres.
 *
 * Périmètre : le siège voit tous les voyages de l'agence ; une autre succursale ne voit que ceux dont la route
 * dessert sa ville (un voyage n'est pas rattaché à une succursale dans le MCD).
 */
final class AgencyTripListing
{
    /** Filtre « état opérationnel » du formulaire => statuts de voyage correspondants. */
    public const STATUS_FILTERS = [
        'scheduled' => [TripStatus::Scheduled],
        'boarding' => [TripStatus::Boarding],
        'departed' => [TripStatus::Departed],
        'completed' => [TripStatus::Arrived],
        'cancelled' => [TripStatus::Cancelled],
    ];

    /** Libellé et classe CSS du badge de chaque statut. */
    private const STATUS_VIEW = [
        'scheduled' => ['Planifié', 'badge-info-status'],
        'boarding' => ['Embarquement', 'badge-warning-status'],
        'departed' => ['En route', 'badge-success-status'],
        'arrived' => ['Terminé', 'badge-success-status'],
        'cancelled' => ['Annulé', 'badge-danger-status'],
    ];

    private const WINDOW_PAST_DAYS = 7;
    private const WINDOW_FUTURE_DAYS = 60;
    private const LIMIT = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TripOccupancy $occupancy,
    ) {
    }

    /**
     * Succursale à laquelle limiter la vue ; null (siège) = toute l'agence.
     */
    public static function scopeBranch(User $user): ?Branch
    {
        $branch = $user->getBranch();

        return null === $branch || AgencyRegistrationService::HEADQUARTERS_CODE === $branch->getCode() ? null : $branch;
    }

    /**
     * @return array{today: int, boarding: int, completed: int}
     */
    public function stats(Agency $agency, ?Branch $branch, \DateTimeImmutable $now): array
    {
        $count = fn (QueryBuilder $qb): int => (int) $qb->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        $today = $now->setTime(0, 0);

        return [
            'today' => $count(
                $this->scoped($agency, $branch)
                    ->andWhere('t.departureDate = :today')
                    ->andWhere('t.status <> :cancelled')
                    ->setParameter('today', $today, Types::DATE_IMMUTABLE)
                    ->setParameter('cancelled', TripStatus::Cancelled->value),
            ),
            'boarding' => $count(
                $this->scoped($agency, $branch)
                    ->andWhere('t.status = :status')
                    ->setParameter('status', TripStatus::Boarding->value),
            ),
            'completed' => $count(
                $this->scoped($agency, $branch)
                    ->andWhere('t.status = :status')
                    ->setParameter('status', TripStatus::Arrived->value),
            ),
        ];
    }

    /**
     * Lignes proposées dans le filtre.
     *
     * @return list<array{id: int, label: string}>
     */
    public function routeOptions(Agency $agency): array
    {
        /** @var list<Route> $routes */
        $routes = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Route::class, 'r')
            ->andWhere('r.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (Route $r): array => ['id' => (int) $r->getId(), 'label' => (string) $r->getName()],
            $routes,
        );
    }

    /**
     * Voyages correspondant aux filtres. Sans aucun filtre : de 7 jours avant à 60 jours après aujourd'hui.
     *
     * @param array{date: ?\DateTimeImmutable, route: ?int, status: ?string} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function search(Agency $agency, ?Branch $branch, array $filters, \DateTimeImmutable $now): array
    {
        $qb = $this->scoped($agency, $branch)->select('t', 'r', 'b', 'd');

        $hasFilter = null !== $filters['date'] || null !== $filters['route'] || null !== $filters['status'];

        if (null !== $filters['date']) {
            $qb->andWhere('t.departureDate = :date')->setParameter('date', $filters['date'], Types::DATE_IMMUTABLE);
        }
        if (null !== $filters['route']) {
            $qb->andWhere('r.id = :route')->setParameter('route', $filters['route']);
        }
        if (null !== $filters['status']) {
            $qb->andWhere('t.status IN (:statuses)')->setParameter(
                'statuses',
                array_map(static fn (TripStatus $s): string => $s->value, self::STATUS_FILTERS[$filters['status']]),
            );
        }
        if (!$hasFilter) {
            $today = $now->setTime(0, 0);
            $qb->andWhere('t.departureDate >= :from')->andWhere('t.departureDate <= :to')
                ->setParameter('from', $today->modify(sprintf('-%d days', self::WINDOW_PAST_DAYS)), Types::DATE_IMMUTABLE)
                ->setParameter('to', $today->modify(sprintf('+%d days', self::WINDOW_FUTURE_DAYS)), Types::DATE_IMMUTABLE);
        }

        // Voyages passés ou annulés : les plus récents d'abord ; sinon les prochains départs d'abord.
        $descending = \in_array($filters['status'], ['completed', 'cancelled'], true);
        $qb->orderBy('t.departureDate', $descending ? 'DESC' : 'ASC')
            ->addOrderBy('t.departureTime', $descending ? 'DESC' : 'ASC')
            ->setMaxResults(self::LIMIT);

        /** @var list<Trip> $trips */
        $trips = $qb->getQuery()->getResult();

        $seats = $this->occupancy->forTrips(array_map(static fn (Trip $t): int => (int) $t->getId(), $trips), $now);

        $rows = [];

        foreach ($trips as $trip) {
            $route = $trip->getRoute();
            $bus = $trip->getBus();
            $date = $trip->getDepartureDate();
            $time = $trip->getDepartureTime();
            $count = $seats[(int) $trip->getId()] ?? ['capacity' => 0, 'taken' => 0];
            [$label, $badge] = self::STATUS_VIEW[$trip->getStatus()->value] ?? [$trip->getStatus()->value, 'badge-info-status'];

            $rows[] = [
                'code' => (string) $trip->getCode(),
                'date' => $date,
                'time' => $time?->format('H\hi'),
                'route' => (string) $route?->getName(),
                'via' => $this->via($route),
                'bus' => (string) ($bus?->getName() ?: $bus?->getCode() ?: $bus?->getPlateNumber()),
                'bus_model' => (string) $bus?->getBrand(),
                'driver' => (string) $trip->getDriver()?->getFullName(),
                'taken' => $count['taken'],
                'capacity' => $count['capacity'],
                'percent' => $count['capacity'] > 0 ? (int) round($count['taken'] / $count['capacity'] * 100) : 0,
                'status_label' => $label,
                'badge' => 'badge '.$badge,
            ];
        }

        return $rows;
    }

    /**
     * Voyages de l'agence (avec, pour une succursale, ceux dont la route dessert sa ville).
     */
    private function scoped(Agency $agency, ?Branch $branch): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->join('t.bus', 'b')
            ->join('t.driver', 'd')
            ->andWhere('r.agency = :agency')
            ->setParameter('agency', $agency);

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
     * Arrêts intermédiaires d'une route, par exemple « Kenge » pour Kinshasa → Kikwit.
     */
    private function via(?Route $route): ?string
    {
        if (null === $route) {
            return null;
        }

        $stops = $route->getRouteStops()->filter(static fn (RouteStop $s): bool => $s->isActive())->getValues();
        $cities = array_map(static fn (RouteStop $s): string => (string) $s->getCity(), \array_slice($stops, 1, -1));

        return [] === $cities ? null : implode(', ', $cities);
    }
}
