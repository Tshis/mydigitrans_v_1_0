<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\line\BookingData;
use App\Entity\Agency;
use App\Entity\BusLayout;
use App\Entity\Fare;
use App\Entity\Reservation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Trip;
use App\Entity\TripSeat;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\FareTargetType;
use App\Enum\FareType;
use App\Enum\ReservationStatus;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use App\Exception\BookingException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion des réservations côté backoffice d'agence (guichet) :
 * liste filtrable, émission de bon de réservation, détail, impression PDF et annulation.
 */
final class AgencyReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookingService $bookingService,
        private readonly BusLayoutGridBuilder $gridBuilder,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function findAgencyTripByCode(Agency $agency, string $tripCode): ?Trip
    {
        return $this->em->createQueryBuilder()
            ->select('t', 'r', 'b', 'bl')
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->join('t.bus', 'b')
            ->leftJoin('b.busLayout', 'bl')
            ->andWhere('r.agency = :agency')
            ->andWhere('t.code = :code')
            ->setParameter('agency', $agency)
            ->setParameter('code', $tripCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAgencyReservationByReference(Agency $agency, string $reference): ?Reservation
    {
        return $this->em->createQueryBuilder()
            ->select('res', 't', 'r', 'b', 'u', 'dep', 'arr', 'cur', 'cb')
            ->from(Reservation::class, 'res')
            ->join('res.trip', 't')
            ->join('t.route', 'r')
            ->join('t.bus', 'b')
            ->join('res.user', 'u')
            ->join('res.departureStop', 'dep')
            ->join('res.arrivalStop', 'arr')
            ->join('res.currency', 'cur')
            ->leftJoin('res.createdBy', 'cb')
            ->andWhere('res.agency = :agency')
            ->andWhere('res.reference = :ref')
            ->setParameter('agency', $agency)
            ->setParameter('ref', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste les réservations de l'agence filtrées par succursale (si l'agent est rattaché à une gare).
     *
     * @param array{query: ?string, status: ?string, routeId: ?int} $filters
     * @return array<string, mixed>
     */
    public function listAgencyReservations(User $actor, array $filters): array
    {
        $agency = $actor->getAgency();
        $branch = $actor->getBranch();
        $now = new \DateTimeImmutable();
        $this->releaseExpiredReservations($agency, $now);

        $today = $now->setTime(0, 0);

        $statsQb = $this->em->createQueryBuilder()
            ->select('res.status AS st', 'COUNT(res.id) AS cnt')
            ->from(Reservation::class, 'res')
            ->join('res.trip', 't')
            ->join('res.departureStop', 'dep')
            ->join('res.arrivalStop', 'arr')
            ->leftJoin('res.createdBy', 'cb')
            ->andWhere('res.agency = :agency')
            ->andWhere('t.departureDate >= :today')
            ->setParameter('agency', $agency)
            ->setParameter('today', $today, \Doctrine\DBAL\Types\Types::DATE_IMMUTABLE)
            ->groupBy('res.status');

        if (null !== $branch) {
            $statsQb->andWhere('(dep.city = :branchCity OR arr.city = :branchCity OR cb.branch = :branch)')
                ->setParameter('branchCity', $branch->getCity())
                ->setParameter('branch', $branch);
        }

        $statsRows = $statsQb->getQuery()->getArrayResult();

        $stats = ['confirmed' => 0, 'pending' => 0, 'cancelled' => 0];
        foreach ($statsRows as $row) {
            $val = $row['st'] instanceof \BackedEnum ? $row['st']->value : (string) $row['st'];
            if (ReservationStatus::Paid->value === $val) {
                $stats['confirmed'] += (int) $row['cnt'];
            } elseif (ReservationStatus::Pending->value === $val) {
                $stats['pending'] += (int) $row['cnt'];
            } elseif (\in_array($val, [ReservationStatus::Cancelled->value, ReservationStatus::Expired->value], true)) {
                $stats['cancelled'] += (int) $row['cnt'];
            }
        }

        $qb = $this->em->createQueryBuilder()
            ->select('res', 't', 'r', 'u', 'dep', 'arr', 'cur', 'cb')
            ->from(Reservation::class, 'res')
            ->join('res.trip', 't')
            ->join('t.route', 'r')
            ->join('res.user', 'u')
            ->join('res.departureStop', 'dep')
            ->join('res.arrivalStop', 'arr')
            ->join('res.currency', 'cur')
            ->leftJoin('res.createdBy', 'cb')
            ->andWhere('res.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('res.createdAt', 'DESC')
            ->setMaxResults(300);

        if (null !== $branch) {
            $qb->andWhere('(dep.city = :branchCity OR arr.city = :branchCity OR cb.branch = :branch)')
                ->setParameter('branchCity', $branch->getCity())
                ->setParameter('branch', $branch);
        }


        if (null !== $filters['query'] && '' !== $filters['query']) {
            $qb->andWhere('(res.reference LIKE :q OR u.phone LIKE :q OR u.firstname LIKE :q OR u.lastname LIKE :q)')
                ->setParameter('q', '%' . $filters['query'] . '%');
        } else {
            // Par défaut : uniquement les réservations sur les départs du jour et à venir
            $qb->andWhere('t.departureDate >= :today')
                ->setParameter('today', $now->setTime(0, 0), \Doctrine\DBAL\Types\Types::DATE_IMMUTABLE);
        }


        if (null !== $filters['status'] && '' !== $filters['status']) {
            $qb->andWhere('res.status = :status')
                ->setParameter('status', $filters['status']);
        } elseif (null === $filters['query'] || '' === $filters['query']) {
            // Par défaut à l'accueil, masquer les réservations annulées et expirées
            $qb->andWhere('res.status NOT IN (:excludedStatuses)')
                ->setParameter('excludedStatuses', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::Expired->value,
                ]);
        }


        if (null !== $filters['routeId']) {
            $qb->andWhere('r.id = :routeId')
                ->setParameter('routeId', $filters['routeId']);
        }

        /** @var list<Reservation> $reservations */
        $reservations = $qb->getQuery()->getResult();

        $rows = [];
        foreach ($reservations as $res) {
            $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['reservation' => $res]);
            $seatLabels = [];
            $isVip = false;
            foreach ($tripSeats as $ts) {
                $seat = $ts->getSeat();
                if (null !== $seat) {
                    $seatLabels[] = $seat->getSeatLabel();
                    if (SeatType::Vip === $seat->getType()) {
                        $isVip = true;
                    }
                }
            }

            $statusVal = $res->getStatus()->value;
            $rows[] = [
                'reference'       => (string) $res->getReference(),
                'sourceLabel'     => null !== $res->getCreatedBy()?->getBranch()
                    ? 'Guichet ' . $res->getCreatedBy()->getBranch()->getName()
                    : 'Réservation en ligne',
                'passengerName'   => (string) $res->getUser()?->getFullName(),
                'passengerPhone'  => (string) $res->getUser()?->getPhone(),
                'departureDate'   => $res->getTrip()?->getDepartureDate(),
                'departureTime'   => $res->getTrip()?->getDepartureTime()?->format('H\hi') ?? '-',
                'segment'         => sprintf('%s → %s', $res->getDepartureStop()?->getCity(), $res->getArrivalStop()?->getCity()),
                'seats'           => [] !== $seatLabels ? implode(', ', $seatLabels) : '-',
                'category'        => $isVip ? 'Classe VIP' : 'Classe Standard',
                'amountFormatted' => sprintf('%s %s', number_format((float) $res->getTotalAmount(), 2, ',', ' '), $res->getCurrency()?->getCode()),
                'status'          => $statusVal,
                'statusLabel'     => match ($statusVal) {
                    'paid'      => 'Confirmée (Payée)',
                    'pending'   => 'En attente de paiement',
                    'cancelled' => 'Annulée',
                    'expired'   => 'Expirée',
                    default     => $statusVal,
                },
            ];
        }

        $routes = $this->em->getRepository(Route::class)->findBy(['agency' => $agency], ['name' => 'ASC']);

        return [
            'stats'        => $stats,
            'reservations' => $rows,
            'routes'       => $routes,
        ];
    }

    /**
     * Prépare les données réelles du formulaire d'ajout de réservation au guichet (arrêts, seatmap, grille tarifaire).
     *
     * @return array<string, mixed>
     */
    public function buildCreateViewData(Trip $trip): array
    {
        $now = new \DateTimeImmutable();
        $route = $trip->getRoute();
        $bus = $trip->getBus();
        $layout = $bus?->getBusLayout();

        // 1. Arrêts actifs ordonnés
        $stops = [];
        if (null !== $route) {
            foreach ($route->getRouteStops() as $stop) {
                if ($stop->isActive()) {
                    $stops[] = $stop;
                }
            }
        }

        // 2. Matrice tarifaire réelle depuis Fare ("depId-arrId" => montant)
        $fares = $this->em->getRepository(Fare::class)->findBy([
            'targetType' => FareTargetType::Route,
            'targetId'   => (int) $route?->getId(),
            'isActive'   => true,
        ]);

        $pricingMatrix = [];
        $vipAmounts = [];
        $stdAmounts = [];
        $currencyCode = 'CDF';

        foreach ($fares as $fare) {
            $currencyCode = $fare->getCurrency()?->getCode() ?? $currencyCode;
            $depId = $fare->getDepartureStop()?->getId();
            $arrId = $fare->getArrivalStop()?->getId();
            if (null === $depId || null === $arrId) {
                continue;
            }
            $key = $depId . '-' . $arrId;
            $amount = (float) $fare->getAmount();

            if (FareType::Vip === $fare->getFareType()) {
                $vipAmounts[$key] = $amount;
            } else {
                $stdAmounts[$key] = $amount;
                $pricingMatrix[$key] = $amount;
            }
        }

        $vipDiff = 0.0;
        foreach ($vipAmounts as $key => $vipPrice) {
            if (isset($stdAmounts[$key])) {
                $vipDiff = max(0.0, $vipPrice - $stdAmounts[$key]);
                break;
            }
        }

        // 3. Construction du seatmap et des statuts réels des sièges du voyage
        $firstStop = reset($stops) ?: new RouteStop();
        $lastStop  = end($stops) ?: new RouteStop();
        $seatInfo  = $this->bookingService->seatInfo($trip, $firstStop, $lastStop, $now);

        $layoutPayload = $layout instanceof BusLayout ? [
            'id'               => $layout->getId(),
            'name'             => $layout->getName(),
            'rows'             => $layout->getRows(),
            'columns'          => $layout->getColumns(),
            'aisles'           => $layout->getAisles(),
            'specialPositions' => $layout->getSpecialPositions(),
            'hasBackExtraSeat' => $layout->hasBackExtraSeat(),
        ] : [
            'rows'             => 10,
            'columns'          => 4,
            'aisles'           => array(2),
            'specialPositions' => [],
            'hasBackExtraSeat' => false,
        ];

        return [
            'trip'            => $trip,
            'trip_code'       => (string) $trip->getCode(),
            'stops'           => $stops,
            'seatmap'         => $this->gridBuilder->build($layoutPayload),
            'seat_info'       => $seatInfo,
            'pricing_matrix'  => $pricingMatrix,
            'fare_categories' => ['seat' => 0, 'vip' => $vipDiff],
            'currency_code'   => $currencyCode,
        ];
    }

    /**
     * Crée un bon de réservation au guichet (statut Pending, bloqué jusqu'à H-2 avant le départ).
     *
     * @throws BookingException
     */
    public function createStationReservation(
        Trip $trip,
        string $departureStopId,
        string $arrivalStopId,
        string $seatNumber,
        string $passengerName,
        string $passengerPhone,
        User $actor
    ): Reservation {
        $now = new \DateTimeImmutable();
        $this->bookingService->assertBookable($trip, $now);
        [$departureStop, $arrivalStop] = $this->bookingService->resolveSegment($trip, $departureStopId, $arrivalStopId);

        $data = new BookingData();
        $data->fullName = trim($passengerName);
        $data->phone    = trim($passengerPhone);
        $data->seats    = array_filter(array_map('trim', explode(',', $seatNumber)));

        if ('' === $data->fullName || '' === $data->phone) {
            throw new BookingException('Le nom complet et le numéro de téléphone du passager sont obligatoires.');
        }

        $reservation = $this->bookingService->createPendingReservation(
            $trip,
            $departureStop,
            $arrivalStop,
            $data,
            $actor,
            $now
        );

        $delayMinutes = $trip->getRoute()?->getReservationExpirationDelay() ?? 120;
        $departureAt = $this->bookingService->departureAt($trip, $departureStop);
        $deadline = $departureAt->modify(sprintf('-%d minutes', $delayMinutes));

        $reservation->setExpiresAt($deadline);

        /** @var list<TripSeat> $tripSeats */
        $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['reservation' => $reservation]);
        foreach ($tripSeats as $ts) {
            $ts->setSeatStatus(TripSeatStatus::Reserved)
                ->setHeldUntil($deadline);
        }

        $this->auditLogger->log(
            action: AuditAction::Create,
            entityName: 'Reservation',
            entityId: $reservation->getId(),
            oldValues: null,
            newValues: [
                'reference' => $reservation->getReference(),
                'trip'      => $trip->getCode(),
                'amount'    => $reservation->getTotalAmount(),
                'status'    => $reservation->getStatus()->value,
            ],
            actor: $actor,
            flush: false
        );

        $this->em->flush();

        return $reservation;
    }

    /**
     * Annule manuellement une réservation en attente et libère immédiatement ses sièges.
     *
     * @throws \DomainException
     */
    public function cancelReservation(Reservation $reservation, string $reason, User $actor): void
    {
        if (ReservationStatus::Pending !== $reservation->getStatus()) {
            throw new \DomainException('Seule une réservation en attente de paiement peut être annulée depuis cet écran.');
        }

        $cleanReason = trim($reason);
        if ('' === $cleanReason) {
            throw new \DomainException('Veuillez indiquer le motif d\'annulation de la réservation.');
        }

        $oldStatus = $reservation->getStatus()->value;

        $reservation->setStatus(ReservationStatus::Cancelled)
            ->setCancellationReason($cleanReason)
            ->setCanceledBy($actor);

        /** @var list<TripSeat> $tripSeats */
        $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['reservation' => $reservation]);
        foreach ($tripSeats as $ts) {
            $ts->setSeatStatus(TripSeatStatus::Available)
                ->setReservation(null)
                ->setHeldUntil(null);
        }

        $this->auditLogger->log(
            action: AuditAction::CancelBooking,
            entityName: 'Reservation',
            entityId: $reservation->getId(),
            oldValues: ['reference' => $reservation->getReference(), 'status' => $oldStatus],
            newValues: ['reference' => $reservation->getReference(), 'status' => ReservationStatus::Cancelled->value, 'reason' => $cleanReason],
            actor: $actor,
            flush: false
        );

        $this->em->flush();
    }

    /**
     * Prépare les données d'affichage de la fiche détaillée et du bon PDF.
     *
     * @return array<string, mixed>
     */
    public function getReservationDetails(Reservation $reservation): array
    {
        $trip = $reservation->getTrip();
        $bus = $trip?->getBus();
        $user = $reservation->getUser();
        $creator = $reservation->getCreatedBy();

        /** @var list<TripSeat> $tripSeats */
        $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['reservation' => $reservation]);
        $seatLabels = [];
        $isVip = false;
        foreach ($tripSeats as $ts) {
            $seat = $ts->getSeat();
            if (null !== $seat) {
                $seatLabels[] = $seat->getSeatLabel();
                if (SeatType::Vip === $seat->getType()) {
                    $isVip = true;
                }
            }
        }

        $statusVal = $reservation->getStatus()->value;

        return [
            'reservation' => [
                'id'                 => $reservation->getId(),
                'reference'          => (string) $reservation->getReference(),
                'status'             => $statusVal,
                'statusLabel'        => match ($statusVal) {
                    'paid'      => 'Confirmée (Payée)',
                    'pending'   => 'En attente de paiement',
                    'cancelled' => 'Annulée',
                    'expired'   => 'Expirée',
                    default     => $statusVal,
                },
                'issuerLabel'        => null !== $creator?->getBranch()
                    ? sprintf('Guichet %s (%s)', $creator->getBranch()->getName(), $creator->getFullName())
                    : sprintf('Réservation en ligne (%s)', $user?->getFullName() ?? '-'),
                'expiresAt'          => $reservation->getExpiresAt(),
                'cancellationReason' => $reservation->getCancellationReason(),
                'passengerName'      => (string) $user?->getFullName(),
                'passengerPhone'     => (string) $user?->getPhone(),
                'tripCode'           => (string) $trip?->getCode(),
                'segment'            => sprintf('%s → %s', $reservation->getDepartureStop()?->getCity(), $reservation->getArrivalStop()?->getCity()),
                'departureStop'      => (string) $reservation->getDepartureStop()?->getCity(),

                'arrivalStop'        => (string) $reservation->getArrivalStop()?->getCity(),
                'departureDate'      => $trip?->getDepartureDate(),
                'departureTime'      => $trip?->getDepartureTime()?->format('H\hi') ?? '-',
                'busLabel'           => trim(sprintf('%s (%s %s)', $bus?->getCode() ?: $bus?->getName(), $bus?->getBrand(), $bus?->getModel())),
                'seatNumber'         => [] !== $seatLabels ? implode(', ', $seatLabels) : '-',
                'fareCategory'       => $isVip ? 'Classe VIP' : 'Classe Standard',
                'amount'             => (float) $reservation->getTotalAmount(),
                'currencyCode'       => (string) $reservation->getCurrency()?->getCode(),
            ],
        ];
    }
    private function releaseExpiredReservations(Agency $agency, \DateTimeImmutable $now): void
    {
        /** @var list $expired */
        $expired = $this->em->createQueryBuilder()
            ->select('res')
            ->from(Reservation::class, 'res')
            ->andWhere('res.agency = :agency')
            ->andWhere('res.status = :pending')
            ->andWhere('res.expiresAt IS NOT NULL')
            ->andWhere('res.expiresAt <= :now')
            ->setParameter('agency', $agency)
            ->setParameter('pending', ReservationStatus::Pending->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
        if ([] === $expired) {
            return;
        }
        foreach ($expired as $res) {
            $res->setStatus(ReservationStatus::Expired);
            $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['reservation' => $res]);
            foreach ($tripSeats as $ts) {
                $ts->setSeatStatus(TripSeatStatus::Available)
                    ->setReservation(null)
                    ->setHeldUntil(null);
            }
        }
        $this->em->flush();
    }
}
