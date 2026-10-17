<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LineSearchData;
use App\Dto\LineSearchResult;
use App\Entity\Fare;
use App\Entity\RouteStop;
use App\Entity\Trip;
use App\Entity\TripSeat;
use App\Enum\BusStatus;
use App\Enum\FareTargetType;
use App\Enum\FareType;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use App\Enum\TripStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Recherche des voyages disponibles entre deux villes à une date donnée.
 *
 * Règles appliquées :
 *  - la ville de départ doit précéder la ville d'arrivée sur la route (stopOrder),
 *    les deux arrêts étant actifs ;
 *  - le voyage part à la date demandée, est « scheduled », sur une route disponible,
 *    pour une agence active et un bus actif ;
 *  - si la date est aujourd'hui, le voyage ne doit pas être déjà parti ;
 *  - il reste au moins autant de places vendables que de passagers ;
 *  - le prix vient du tarif actif le plus précis (arrêts exacts avant tarif de route).
 */
final class LineSearcher
{
    /** Types de sièges qui peuvent être vendus à un passager. */
    private const SELLABLE_SEAT_TYPES = [SeatType::Normal, SeatType::Vip, SeatType::Extra];

    /** Types de tarifs affichés dans les résultats (le tarif VIP concerne les sièges VIP). */
    private const SEARCH_FARE_TYPES = [FareType::Standard, FareType::Promo];

    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @return list<LineSearchResult> triés par heure de départ
     */
    public function search(LineSearchData $criteria, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        if (null === $criteria->departure || null === $criteria->arrival || null === $criteria->date) {
            return [];
        }

        $passengers = max(1, $criteria->passengers ?? 1);

        $segments = $this->findSegments(
            $this->normalize($criteria->departure),
            $this->normalize($criteria->arrival),
        );
        if ([] === $segments) {
            return [];
        }

        $routeIds = array_keys($segments);

        $trips = $this->findTrips($routeIds, $criteria->date, $now);
        if ([] === $trips) {
            return [];
        }

        $seatCounts = $this->countAvailableSeats(
            array_map(static fn(Trip $trip): int => (int) $trip->getId(), $trips),
            $now,
        );
        $faresByRoute = $this->findFares($routeIds, $now);

        $results = [];

        foreach ($trips as $trip) {
            $tripId = (int) $trip->getId();
            $routeId = (int) $trip->getRoute()?->getId();
            $freeSeats = $seatCounts[$tripId] ?? 0;
            $departureDate = $trip->getDepartureDate();
            $departureTime = $trip->getDepartureTime();

            if ($freeSeats < $passengers || !isset($segments[$routeId]) || null === $departureDate || null === $departureTime) {
                continue;
            }

            $segment = $segments[$routeId];
            $fare = $this->pickFare($faresByRoute[$routeId] ?? [], $segment['departureStopId'], $segment['arrivalStopId']);

            // Heure de départ de la route, puis décalage jusqu'aux arrêts demandés.
            $origin = $departureDate->setTime((int) $departureTime->format('H'), (int) $departureTime->format('i'));

            $results[] = new LineSearchResult(
                trip: $trip,
                departureStopId: $segment['departureStopId'],
                arrivalStopId: $segment['arrivalStopId'],
                departureCity: $segment['departureCity'],
                arrivalCity: $segment['arrivalCity'],
                departureAt: $origin->modify(sprintf('+%d minutes', $segment['departureOffset'])),
                arrivalAt: $origin->modify(sprintf('+%d minutes', $segment['arrivalOffset'])),
                availableSeats: $freeSeats,
                passengers: $passengers,
                unitPrice: $fare?->getAmount(),
                currency: $fare?->getCurrency(),
            );
        }

        usort($results, static fn(LineSearchResult $a, LineSearchResult $b): int => $a->departureAt <=> $b->departureAt);

        return $results;
    }

    /**
     * Routes qui desservent les deux villes dans le bon sens.
     *
     * @return array<int, array{departureStopId: int, arrivalStopId: int, departureCity: string, arrivalCity: string, departureOffset: int, arrivalOffset: int}>
     *         indexé par id de route (un seul tronçon par route)
     */
    private function findSegments(string $departure, string $arrival): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select(
                'IDENTITY(ds.route) AS routeId',
                'ds.id AS departureStopId',
                'ds.city AS departureCity',
                'ds.durationFromDeparture AS departureOffset',
                'ars.id AS arrivalStopId',
                'ars.city AS arrivalCity',
                'ars.durationFromDeparture AS arrivalOffset',
            )
            ->from(RouteStop::class, 'ds')
            ->join(RouteStop::class, 'ars', 'WITH', 'IDENTITY(ars.route) = IDENTITY(ds.route)')
            ->andWhere('ds.isActive = :active')
            ->andWhere('ars.isActive = :active')
            ->andWhere('LOWER(ds.city) = :departure')
            ->andWhere('LOWER(ars.city) = :arrival')
            ->andWhere('ds.stopOrder < ars.stopOrder')
            ->setParameter('active', true)
            ->setParameter('departure', $departure)
            ->setParameter('arrival', $arrival)
            ->orderBy('ds.stopOrder', 'ASC')
            ->addOrderBy('ars.stopOrder', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $segments = [];

        foreach ($rows as $row) {
            $routeId = (int) $row['routeId'];

            $segments[$routeId] ??= [
                'departureStopId' => (int) $row['departureStopId'],
                'arrivalStopId' => (int) $row['arrivalStopId'],
                'departureCity' => (string) $row['departureCity'],
                'arrivalCity' => (string) $row['arrivalCity'],
                'departureOffset' => (int) $row['departureOffset'],
                'arrivalOffset' => (int) $row['arrivalOffset'],
            ];
        }

        return $segments;
    }

    /**
     * @param list<int> $routeIds
     *
     * @return list<Trip>
     */
    private function findTrips(array $routeIds, \DateTimeImmutable $date, \DateTimeImmutable $now): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t', 'r', 'a', 'b')
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->join('r.agency', 'a')
            ->join('t.bus', 'b')
            ->andWhere('IDENTITY(t.route) IN (:routeIds)')
            ->andWhere('t.departureDate = :date')
            ->andWhere('t.status = :tripStatus')
            ->andWhere('r.isAvailable = :yes')
            ->andWhere('a.isActive = :yes')
            ->andWhere('b.status = :busStatus')
            ->setParameter('routeIds', $routeIds)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('tripStatus', TripStatus::Scheduled->value)
            ->setParameter('busStatus', BusStatus::Active->value)
            ->setParameter('yes', true)
            ->orderBy('t.departureTime', 'ASC');

        // Recherche pour aujourd'hui : on écarte les voyages déjà partis.
        if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
            $qb->andWhere('t.departureTime > :nowTime')
                ->setParameter('nowTime', $now, Types::TIME_IMMUTABLE);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre de places vendables par voyage. Une place « held » dont le délai
     * est dépassé compte comme libre (le nettoyage peut avoir un peu de retard).
     *
     * @param list<int> $tripIds
     *
     * @return array<int, int> nombre de places libres, indexé par id de voyage
     */
    private function countAvailableSeats(array $tripIds, \DateTimeImmutable $now): array
    {
        $qb = $this->em->createQueryBuilder();

        $rows = $qb
            ->select('IDENTITY(ts.trip) AS tripId', 'COUNT(ts.id) AS freeSeats')
            ->from(TripSeat::class, 'ts')
            ->join('ts.seat', 's')
            ->andWhere('IDENTITY(ts.trip) IN (:tripIds)')
            ->andWhere('s.type IN (:seatTypes)')
            ->andWhere($qb->expr()->orX(
                'ts.seatStatus = :available',
                '(ts.seatStatus = :held AND ts.heldUntil IS NOT NULL AND ts.heldUntil < :now)',
            ))
            ->setParameter('tripIds', $tripIds)
            ->setParameter('seatTypes', array_map(static fn(SeatType $type): string => $type->value, self::SELLABLE_SEAT_TYPES))
            ->setParameter('available', TripSeatStatus::Available->value)
            ->setParameter('held', TripSeatStatus::Held->value)
            ->setParameter('now', $now)
            ->groupBy('ts.trip')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['tripId']] = (int) $row['freeSeats'];
        }

        return $counts;
    }

    /**
     * @param list<int> $routeIds
     *
     * @return array<int, list<Fare>> tarifs actifs, indexés par id de route
     */
    private function findFares(array $routeIds, \DateTimeImmutable $now): array
    {
        $qb = $this->em->createQueryBuilder();

        /** @var list<Fare> $fares */
        $fares = $qb
            ->select('f', 'c')
            ->from(Fare::class, 'f')
            ->join('f.currency', 'c')
            ->andWhere('f.targetType = :targetType')
            ->andWhere('f.targetId IN (:routeIds)')
            ->andWhere('f.fareType IN (:fareTypes)')
            ->andWhere('f.isActive = :active')
            ->andWhere('f.validFrom <= :now')
            ->andWhere($qb->expr()->orX('f.validUntil IS NULL', 'f.validUntil >= :now'))
            ->setParameter('targetType', FareTargetType::Route->value)
            ->setParameter('routeIds', $routeIds)
            ->setParameter('fareTypes', array_map(static fn(FareType $type): string => $type->value, self::SEARCH_FARE_TYPES))
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $byRoute = [];

        foreach ($fares as $fare) {
            $byRoute[(int) $fare->getTargetId()][] = $fare;
        }

        return $byRoute;
    }

    /**
     * Tarif propre au tronçon (mêmes arrêts de montée et de descente) s'il existe,
     * sinon tarif général de la route (sans arrêts). Le moins cher l'emporte.
     *
     * @param list<Fare> $fares
     */
    private function pickFare(array $fares, int $departureStopId, int $arrivalStopId): ?Fare
    {
        $specific = [];
        $generic = [];

        foreach ($fares as $fare) {
            $fromId = $fare->getDepartureStop()?->getId();
            $toId = $fare->getArrivalStop()?->getId();

            if (null === $fromId && null === $toId) {
                $generic[] = $fare;
            } elseif ($fromId === $departureStopId && $toId === $arrivalStopId) {
                $specific[] = $fare;
            }
        }

        $pool = [] !== $specific ? $specific : $generic;

        if ([] === $pool) {
            return null;
        }

        usort($pool, static fn(Fare $a, Fare $b): int => (float) $a->getAmount() <=> (float) $b->getAmount());

        return $pool[0];
    }

    private function normalize(string $city): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $city)));
    }
}
