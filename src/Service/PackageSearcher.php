<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\package\PackageSearchData;
use App\Dto\public\package\PackageSearchResult;
use App\Entity\Branch;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Trip;
use App\Enum\BranchStatus;
use App\Enum\BusStatus;
use App\Enum\PackageWeightRange;
use App\Enum\ShipmentStatus;
use App\Enum\TripStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Recherche des départs sur lesquels un colis peut être confié entre deux villes.
 */
final class PackageSearcher
{
    public const DEFAULT_HOLD_CAPACITY_KG = 500;
    private const HORIZON_DAYS = 30;
    private const MAX_RESULTS = 30;
    private const DROP_OFF_DELAY = '+1 hour';

    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @return list<PackageSearchResult> triés par heure de départ
     */
    public function search(PackageSearchData $criteria, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        if (null === $criteria->departure || null === $criteria->arrival || null === $criteria->weightRange) {
            return [];
        }

        $segments = $this->findSegments(
            $this->normalize($criteria->departure),
            $this->normalize($criteria->arrival),
        );
        if ([] === $segments) {
            return [];
        }

        $today = $now->setTime(0, 0);
        $from = $criteria->date ?? $today;
        $to = $criteria->date ?? $today->modify(sprintf('+%d days', self::HORIZON_DAYS));

        $trips = $this->findTrips(array_keys($segments), $from, $to);
        if ([] === $trips) {
            return [];
        }

        $usedKg = $this->usedCapacity(array_map(static fn(Trip $trip): int => (int) $trip->getId(), $trips));
        $branches = $this->findBranches($trips);
        $cutoff = $now->modify(self::DROP_OFF_DELAY);
        $needKg = $criteria->weightRange->requiredCapacityKg();

        $results = [];

        foreach ($trips as $trip) {
            $route = $trip->getRoute();
            $agency = $route?->getAgency();
            $bus = $trip->getBus();
            $date = $trip->getDepartureDate();
            $time = $trip->getDepartureTime();

            if (null === $route || null === $agency || null === $bus || null === $date || null === $time) {
                continue;
            }

            $segment = $segments[(int) $route->getId()] ?? null;
            if (null === $segment) {
                continue;
            }

            // Heure de départ de la route, puis décalage arithmétique d'intervalles jusqu'aux gares demandées.
            $baseDate = \DateTimeImmutable::createFromInterface($date);
            $origin = $baseDate->setTime((int) $time->format('H'), (int) $time->format('i'), 0);

            $departureAt = $origin->add(new \DateInterval(sprintf('PT%dM', $segment['departureOffset'])));
            $arrivalAt = $origin->add(new \DateInterval(sprintf('PT%dM', $segment['arrivalOffset'])));

            if ($departureAt < $cutoff) {
                continue;
            }

            $remainingKg = (int) floor(self::DEFAULT_HOLD_CAPACITY_KG - ($usedKg[(int) $trip->getId()] ?? 0.0));
            if ($remainingKg < $needKg) {
                continue;
            }

            $agencyBranches = $branches[(int) $agency->getId()] ?? [];
            $departureBranch = $agencyBranches[$this->normalize($segment['departureCity'])] ?? null;
            $arrivalBranch = $agencyBranches[$this->normalize($segment['arrivalCity'])] ?? null;
            if (null === $departureBranch || null === $arrivalBranch) {
                continue;
            }

            [$minFare, $maxFare] = $this->estimateFare($route, $criteria->weightRange);

            $results[] = new PackageSearchResult(
                trip: $trip,
                agency: $agency,
                busModel: trim($bus->getBrand() . ' ' . $bus->getModel()),
                departureBranch: $departureBranch,
                arrivalBranch: $arrivalBranch,
                departureAt: $departureAt,
                arrivalAt: $arrivalAt,
                remainingCapacityKg: $remainingKg,
                minFare: $minFare,
                maxFare: $maxFare,
                currency: null === $minFare ? null : ($agency->getDefaultCurrency()?->getCode() ?? 'USD'), // CORRECTION : Accès via la devise par défaut de l'agence du MCD
            );
        }

        usort($results, static fn(PackageSearchResult $a, PackageSearchResult $b): int => $a->departureAt <=> $b->departureAt);

        return array_slice($results, 0, self::MAX_RESULTS);
    }

    /**
     * Routes qui desservent les deux villes dans le bon sens.
     *
     * @return array<int, array{departureCity: string, arrivalCity: string, departureOffset: int, arrivalOffset: int}>
     */
    private function findSegments(string $departure, string $arrival): array
    {
        // CORRECTION : Extraction directe depuis la propriété string 'city' de RouteStop, en conformité avec ton entité
        $rows = $this->em->createQueryBuilder()
            ->select(
                'IDENTITY(ds.route) AS routeId',
                'ds.city AS departureCity',
                'ds.durationFromDeparture AS departureOffset',
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
            $segments[$routeId] = [
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
     * @return list<Trip>
     */
    private function findTrips(array $routeIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('t', 'r', 'a', 'b')
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->join('r.agency', 'a')
            ->join('t.bus', 'b')
            ->andWhere('IDENTITY(t.route) IN (:routeIds)')
            ->andWhere('t.departureDate >= :from')
            ->andWhere('t.departureDate <= :to')
            ->andWhere('t.status = :tripStatus')
            ->andWhere('r.isAvailable = :yes')
            ->andWhere('a.isActive = :yes')
            ->andWhere('b.status = :busStatus')
            ->setParameter('routeIds', $routeIds)
            ->setParameter('from', $from->format('Y-m-d')) // CORRECTION : Passage au format String stable
            ->setParameter('to', $to->format('Y-m-d'))     // CORRECTION : Passage au format String stable
            ->setParameter('tripStatus', TripStatus::Scheduled->value)
            ->setParameter('busStatus', BusStatus::Active->value)
            ->setParameter('yes', true)
            ->orderBy('t.departureDate', 'ASC')
            ->addOrderBy('t.departureTime', 'ASC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }

    /**
     * Poids déjà confié par voyage (colis non annulés).
     *
     * @param list<int> $tripIds
     * @return array<int, float> kilos utilisés, indexés par id de voyage
     */
    private function usedCapacity(array $tripIds): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(s.trip) AS tripId', 'COALESCE(SUM(s.weight), 0) AS usedKg')
            ->from(Shipment::class, 's')
            ->andWhere('IDENTITY(s.trip) IN (:tripIds)')
            ->andWhere('s.status <> :cancelled')
            ->setParameter('tripIds', $tripIds)
            ->setParameter('cancelled', ShipmentStatus::Cancelled->value)
            ->groupBy('tripId') // CORRECTION : Regroupement sur l'alias DQL pour écarter l'exception de syntaxe
            ->getQuery()
            ->getArrayResult();

        $used = [];
        foreach ($rows as $row) {
            $used[(int) $row['tripId']] = (float) $row['usedKg'];
        }

        return $used;
    }

    /**
     * Succursales ouvertes des agences concernées.
     *
     * @param list<Trip> $trips
     * @return array<int, array<string, Branch>>
     */
    private function findBranches(array $trips): array
    {
        $agencyIds = [];
        foreach ($trips as $trip) {
            $id = $trip->getRoute()?->getAgency()?->getId();
            if (null !== $id) {
                $agencyIds[$id] = $id;
            }
        }

        if ([] === $agencyIds) {
            return [];
        }

        /** @var list<Branch> $branches */
        $branches = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Branch::class, 'b')
            ->andWhere('IDENTITY(b.agency) IN (:agencyIds)')
            ->andWhere('b.status = :opened')
            ->setParameter('agencyIds', array_values($agencyIds))
            ->setParameter('opened', BranchStatus::Opened->value)
            ->orderBy('b.code', 'ASC')
            ->getQuery()

            ->getResult();
        $byAgency = [];
        foreach ($branches as $branch) {
            $agencyId = (int) $branch->getAgency()?->getId();
            $byAgency[$agencyId][$this->normalize((string) $branch->getCity())] ??= $branch;
        }
        return $byAgency;
    }
    /**
     * Estimation du tarif d'après le prix au kilo de la route.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function estimateFare(Route $route, PackageWeightRange $range): array
    {
        $perKg = $route->getPricePerKg();
        if (null === $perKg) {
            return [null, null];
        }
        $min = number_format($range->minKg() * (float) $perKg, 2, '.', '');
        $max = null === $range->maxKg() ? null : number_format($range->maxKg() * (float) $perKg, 2, '.', '');
        return [$min, $max];
    }
    private function normalize(string $city): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $city)));
    }
}
