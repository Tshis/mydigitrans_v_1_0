<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agency;
use App\Entity\Reservation;
use App\Entity\RouteStop;
use App\Entity\Ticket;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\ReservationStatus;
use App\Enum\SeatType;
use App\Enum\TicketStatus;
use App\Enum\TripSeatStatus;
use App\Enum\TripStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Opérations sur un voyage existant : fiche détaillée, transitions de statut et manifeste passagers.
 */
final class TripOperationService
{
    private const SELLABLE_SEAT_TYPES = [SeatType::Normal, SeatType::Vip, SeatType::Extra];

    private const STATUS_META = [
        'scheduled' => ['Planifié', 'badge-info-status'],
        'boarding'  => ['Embarquement en cours', 'badge-warning-status'],
        'departed'  => ['En route', 'badge-success-status'],
        'arrived'   => ['Arrivé / Terminé', 'badge-success-status'],
        'cancelled' => ['Annulé', 'badge-danger-status'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function findAgencyTripByCode(Agency $agency, string $code): ?Trip
    {
        return $this->em->createQueryBuilder()
            ->select('t', 'r', 'b', 'd', 'cd', 'depBr', 'arrBr')
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->join('t.bus', 'b')
            ->join('t.driver', 'd')
            ->leftJoin('t.coDriver', 'cd')
            ->leftJoin('t.departureBranch', 'depBr')
            ->leftJoin('t.arrivalBranch', 'arrBr')
            ->andWhere('r.agency = :agency')
            ->andWhere('t.code = :code')
            ->setParameter('agency', $agency)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compile toutes les données réelles d'un voyage et son manifeste passagers.
     *
     * @return array<string, mixed>
     */
    public function getTripDetails(Trip $trip): array
    {
        $route = $trip->getRoute();
        $bus   = $trip->getBus();

        $capacity = 0;
        foreach ($trip->getTripSeats() as $ts) {
            if (\in_array($ts->getSeat()?->getType(), self::SELLABLE_SEAT_TYPES, true)) {
                ++$capacity;
            }
        }

        $viaCities = [];
        if (null !== $route) {
            $stops = $route->getRouteStops()->filter(static fn(RouteStop $s): bool => $s->isActive())->getValues();
            $viaCities = array_map(static fn(RouteStop $s): string => (string) $s->getCity(), \array_slice($stops, 1, -1));
        }

        $statusValue = $trip->getStatus()->value;
        [$statusLabel, $badgeClass] = self::STATUS_META[$statusValue] ?? [$statusValue, 'badge-info-status'];

        return [
            'trip' => [
                'id'              => $trip->getId(),
                'code'            => (string) $trip->getCode(),
                'tripNumber'      => strtoupper((string) $trip->getCode()),
                'route'           => (string) $route?->getName(),
                'via'             => [] !== $viaCities ? implode(', ', $viaCities) : null,
                'departureBranch' => null !== $trip->getDepartureBranch()
                    ? sprintf('%s (%s)', $trip->getDepartureBranch()->getName(), $trip->getDepartureBranch()->getCity())
                    : 'Toute gare de la ville de départ',
                'arrivalBranch'   => null !== $trip->getArrivalBranch()
                    ? sprintf('%s (%s)', $trip->getArrivalBranch()->getName(), $trip->getArrivalBranch()->getCity())
                    : 'Terminus standard',
                'departureDate'   => $trip->getDepartureDate(),
                'departureTime'   => $trip->getDepartureTime()?->format('H\hi') ?? '-',
                'arrivalDate'     => $trip->getArrivalDate(),
                'arrivalTime'     => $trip->getArrivalTime()?->format('H\hi') ?? '-',
                'bus'             => trim(sprintf('%s (%s %s)', $bus?->getCode() ?: $bus?->getName(), $bus?->getBrand(), $bus?->getModel())),
                'plate'           => (string) $bus?->getPlateNumber(),
                'capacity'        => $capacity,
                'driver'          => (string) $trip->getDriver()?->getFullName(),
                'coDriver'        => $trip->getCoDriver()?->getFullName() ?? 'Aucun',
                'status'          => $statusValue,
                'statusLabel'     => $statusLabel,
                'badgeClass'      => $badgeClass,
            ],
            'passengers_list' => $this->getTripManifest($trip),
        ];
    }

    /**
     * Extrait les passagers confirmés (billets émis) et réservés (guichet + public) sur ce voyage.
     *
     * @return list<array<string, mixed>>
     */
    public function getTripManifest(Trip $trip): array
    {
        $passengers = [];
        $now = new \DateTimeImmutable();

        // 1. Billets payés/actifs émis sur ce voyage
        /** @var list<Ticket> $tickets */
        $tickets = $this->em->createQueryBuilder()
            ->select('tk', 'ts', 's', 'res', 'dep', 'arr')
            ->from(Ticket::class, 'tk')
            ->join('tk.tripSeat', 'ts')
            ->join('ts.seat', 's')
            ->join('tk.reservation', 'res')
            ->leftJoin('res.departureStop', 'dep')
            ->leftJoin('res.arrivalStop', 'arr')
            ->andWhere('ts.trip = :trip')
            ->andWhere('tk.status IN (:statuses)')
            ->setParameter('trip', $trip)
            ->setParameter('statuses', [TicketStatus::Paid->value, TicketStatus::Used->value])
            ->orderBy('s.seatLabel', 'ASC')
            ->getQuery()
            ->getResult();

        $coveredSeatIds = [];
        foreach ($tickets as $ticket) {
            $seat = $ticket->getTripSeat()?->getSeat();
            $res  = $ticket->getReservation();
            if (null !== $seat?->getId()) {
                $coveredSeatIds[$seat->getId()] = true;
            }

            $passengers[] = [
                'seat'          => (string) $seat?->getSeatLabel(),
                'category'      => SeatType::Vip === $seat?->getType() ? 'VIP' : 'Std',
                'name'          => (string) $ticket->getTravelerName(),
                'route_segment' => sprintf('%s → %s', $res?->getDepartureStop()?->getCity() ?? '-', $res?->getArrivalStop()?->getCity() ?? '-'),
                'phone'         => (string) $ticket->getTravelerPhone(),
                'status'        => 'confirmed',
            ];
        }

        // 2. Sièges réservés en attente de paiement (guichet Reserved + retenues publiques Held en cours)
        foreach ($trip->getTripSeats() as $ts) {
            $seat = $ts->getSeat();
            $res  = $ts->getReservation();
            if (null === $seat || isset($coveredSeatIds[$seat->getId()]) || !$res instanceof Reservation) {
                continue;
            }

            $isActiveHold = TripSeatStatus::Held === $ts->getSeatStatus()
                && (null === $ts->getHeldUntil() || $ts->getHeldUntil() >= $now);

            if ((TripSeatStatus::Reserved === $ts->getSeatStatus() || $isActiveHold) && ReservationStatus::Pending === $res->getStatus()) {
                $client = $res->getUser();
                $passengers[] = [
                    'seat'          => (string) $seat->getSeatLabel(),
                    'category'      => SeatType::Vip === $seat->getType() ? 'VIP' : 'Std',
                    'name'          => $client?->getFullName() ?? 'Client réservé',
                    'route_segment' => sprintf('%s → %s', $res->getDepartureStop()?->getCity() ?? '-', $res->getArrivalStop()?->getCity() ?? '-'),
                    'phone'         => $client?->getPhone() ?? '-',
                    'status'        => 'pending',
                ];
            }
        }

        usort($passengers, static fn(array $a, array $b): int => strnatcmp((string) $a['seat'], (string) $b['seat']));

        return $passengers;
    }



    /**
     * Fait évoluer le statut du voyage (boarding, departed, arrived, cancelled) avec protections métier et AuditLog.
     *
     * @throws \DomainException
     */
    public function transitionStatus(Trip $trip, TripStatus $targetStatus, User $actor, ?string $reason = null): void
    {
        $current = $trip->getStatus();

        if ($current === $targetStatus) {
            return;
        }

        if (\in_array($current, [TripStatus::Arrived, TripStatus::Cancelled], true)) {
            throw new \DomainException('Ce voyage est déjà clôturé ou annulé.');
        }

        if (TripStatus::Boarding === $targetStatus) {
            $this->assertCanOpenBoarding($trip, $actor);
        }

        $oldStatusValue = $current->value;
        $trip->setStatus($targetStatus);

        $auditAction = TripStatus::Cancelled === $targetStatus ? AuditAction::Cancel : AuditAction::Update;

        $newValues = [
            'code'   => $trip->getCode(),
            'status' => $targetStatus->value,
        ];

        if (null !== $reason && '' !== trim($reason)) {
            $newValues['reason'] = trim($reason);
        }

        $this->auditLogger->log(
            action: $auditAction,
            entityName: 'Trip',
            entityId: $trip->getId(),
            oldValues: [
                'code'   => $trip->getCode(),
                'status' => $oldStatusValue,
            ],
            newValues: $newValues,
            actor: $actor,
            flush: false
        );

        $this->em->flush();
    }


    /**
     * Vérifie les conditions temporelles et de succursale avant d'ouvrir l'embarquement.
     *
     * @throws \DomainException
     */
    private function assertCanOpenBoarding(Trip $trip, User $actor): void
    {
        // 1. Contrôle de la gare d'embarquement si elle est définie sur le voyage
        $departureBranch = $trip->getDepartureBranch();
        if (null !== $departureBranch) {
            $actorBranch = $actor->getBranch();
            $isHeadquarters = null === $actorBranch
                || AgencyRegistrationService::HEADQUARTERS_CODE === $actorBranch->getCode();

            if (!$isHeadquarters && $actorBranch->getId() !== $departureBranch->getId()) {
                throw new \DomainException(sprintf(
                    'Seul un responsable de la gare d\'embarquement « %s » (ou du siège) peut ouvrir l\'embarquement de ce voyage.',
                    $departureBranch->getName()
                ));
            }
        }
        // 2. Contrôle temporel : au plus tôt 6 heures avant l'heure prévue de départ
        $depDate = $trip->getDepartureDate();
        $depTime = $trip->getDepartureTime();
        if (null !== $depDate && null !== $depTime) {
            $departureAt = \DateTimeImmutable::createFromInterface($depDate)
                ->setTime((int) $depTime->format('H'), (int) $depTime->format('i'));
            $earliestBoarding = $departureAt->modify('-6 hours');
            $now = new \DateTimeImmutable();
            if ($now < $earliestBoarding) {
                throw new \DomainException(sprintf(
                    'L\'embarquement ne peut pas être ouvert avant le %s (6 heures avant le départ).',
                    $earliestBoarding->format('d/m/Y à H\hi')
                ));
            }
        }
    }
}
