<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\package\PackageSearchData;
use App\Dto\public\package\PackageSearchResult;
use App\Entity\Branch;
use App\Entity\CurrencyAgency;
use App\Entity\Fare;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Trip;
use App\Enum\BranchStatus;
use App\Enum\BusStatus;
use App\Enum\FareTargetType;
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
        $baseCurrencies = $this->findAgencyBaseCurrencies($trips);
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

            $baseDate = \DateTimeImmutable::createFromInterface($date);
            $origin = $baseDate->setTime((int) $time->format('H'), (int) $time->format('i'), 0);

            $departureAt = $origin->add(new \DateInterval(sprintf('PT%dM', $segment['departureOffset'])));
            $arrivalAt = $origin->add(new \DateInterval(sprintf('PT%dM', $segment['arrivalOffset'])));

            if (0 === $segment['arrivalOffset'] && null !== $trip->getArrivalDate() && null !== $trip->getArrivalTime()) {
                $arrivalAt = \DateTimeImmutable::createFromInterface($trip->getArrivalDate())
                    ->setTime((int) $trip->getArrivalTime()->format('H'), (int) $trip->getArrivalTime()->format('i'), 0);
            }

            if ($departureAt < $cutoff) {
                continue;
            }

            $remainingKg = (int) floor(self::DEFAULT_HOLD_CAPACITY_KG - ($usedKg[(int) $trip->getId()] ?? 0.0));
            if ($remainingKg < $needKg) {
                continue;
            }

            $agencyBranches = $branches[(int) $agency->getId()] ?? [];

            $tripDepBranch = $trip->getDepartureBranch();
            $departureBranch = (null !== $tripDepBranch && $this->normalize((string) $tripDepBranch->getCity()) === $this->normalize($segment['departureCity']))
                ? $tripDepBranch
                : ($agencyBranches[$this->normalize($segment['departureCity'])] ?? (new Branch())->setName('Gare ' . $segment['departureCity'])->setCity($segment['departureCity']));

            $tripArrBranch = $trip->getArrivalBranch();
            $arrivalBranch = (null !== $tripArrBranch && $this->normalize((string) $tripArrBranch->getCity()) === $this->normalize($segment['arrivalCity']))
                ? $tripArrBranch
                : ($agencyBranches[$this->normalize($segment['arrivalCity'])] ?? (new Branch())->setName('Gare ' . $segment['arrivalCity'])->setCity($segment['arrivalCity']));

            $fallbackCurrency = $baseCurrencies[(int) $agency->getId()] ?? 'CDF';
            [$minFare, $maxFare, $currencyCode] = $this->estimateFare(
                $route,
                $segment['departureStopId'],
                $segment['arrivalStopId'],
                $criteria->weightRange,
                $fallbackCurrency
            );

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
                currency: null === $minFare ? null : $currencyCode,
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
                'departureCity'   => (string) $row['departureCity'],
                'arrivalStopId'   => (int) $row['arrivalStopId'],
                'arrivalCity'     => (string) $row['arrivalCity'],
                'departureOffset' => (int) $row['departureOffset'],
                'arrivalOffset'   => (int) $row['arrivalOffset'],
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
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
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
     * @return array<int, float>
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
            ->groupBy('tripId')
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
     * Devise de base configurée pour chaque agence via CurrencyAgency.
     *
     * @param list<Trip> $trips
     * @return array<int, string>
     */
    private function findAgencyBaseCurrencies(array $trips): array
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

        /** @var list<CurrencyAgency> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('ca', 'c')
            ->from(CurrencyAgency::class, 'ca')
            ->join('ca.currency', 'c')
            ->andWhere('IDENTITY(ca.agency) IN (:agencyIds)')
            ->andWhere('ca.isBase = :yes')
            ->andWhere('ca.isActive = :yes')
            ->setParameter('agencyIds', array_values($agencyIds))
            ->setParameter('yes', true)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $agencyId = (int) $row->getAgency()?->getId();
            $code = $row->getCurrency()?->getCode();
            if ($agencyId > 0 && null !== $code) {
                $map[$agencyId] = $code;
            }
        }

        return $map;
    }

    /**
     * Estimation du tarif réel par segment depuis la table Fare (price_per_kg + currency),
     * avec repli sur Route::pricePerKg si aucun tarif segmentaire n'est défini.
     *
     * @return array{0: ?string, 1: ?string, 2: string}
     */
    private function estimateFare(
        Route $route,
        int $departureStopId,
        int $arrivalStopId,
        PackageWeightRange $range,
        string $fallbackCurrency
    ): array {
        /** @var list<Fare> $fares */
        $fares = $this->em->createQueryBuilder()
            ->select('f', 'c')
            ->from(Fare::class, 'f')
            ->leftJoin('f.currency', 'c')
            ->andWhere('f.targetType = :targetType')
            ->andWhere('f.targetId = :routeId')
            ->andWhere('IDENTITY(f.departureStop) = :depId')
            ->andWhere('IDENTITY(f.arrivalStop) = :arrId')
            ->andWhere('f.isActive = :yes')
            ->andWhere('f.pricePerKg IS NOT NULL')
            ->setParameter('targetType', FareTargetType::Route)
            ->setParameter('routeId', (int) $route->getId())
            ->setParameter('depId', $departureStopId)
            ->setParameter('arrId', $arrivalStopId)
            ->setParameter('yes', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        $perKg = null;
        $currency = $fallbackCurrency;

        if ([] !== $fares && null !== $fares[0]->getPricePerKg()) {
            $perKg = (float) $fares[0]->getPricePerKg();
            $currency = $fares[0]->getCurrency()?->getCode() ?? $fallbackCurrency;
        } elseif (null !== $route->getPricePerKg()) {
            $perKg = (float) $route->getPricePerKg();
            if (method_exists($route, 'getCurrencyPricePerKg') && null !== $route->getCurrencyPricePerKg()) {
                $currency = $route->getCurrencyPricePerKg()->getCode() ?? $fallbackCurrency;
            }
        }

        if (null === $perKg || $perKg <= 0.0) {
            return [null, null, $currency];
        }

        $min = number_format($range->minKg() * $perKg, 2, '.', '');
        $max = null === $range->maxKg() ? null : number_format($range->maxKg() * $perKg, 2, '.', '');

        return [$min, $max, $currency];
    }


    private function normalize(string $city): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $city)));
    }
}
