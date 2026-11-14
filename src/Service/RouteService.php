<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\RouteData;
use App\Entity\Agency;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\Fare;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Enum\FareTargetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Service de gestion des lignes interurbaines, escales (RouteStop) et activation réseau.
 */
final class RouteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Retourne les devises actives de l'agence pour alimenter le ChoiceType du formulaire.
     *
     * @return array<string, string>
     */
    public function getAgencyCurrencyChoices(Agency $agency): array
    {
        $relations = $this->em->getRepository(CurrencyAgency::class)->findBy([
            'agency'   => $agency,
            'isActive' => true,
        ]);

        $choices = [];
        foreach ($relations as $rel) {
            $currency = $rel->getCurrency();
            if ($currency instanceof Currency) {
                $label = sprintf('%s (%s)', $currency->getCode(), $currency->getName());
                $choices[$label] = (string) $currency->getCode();
            }
        }

        if ([] === $choices) {
            $all = $this->em->getRepository(Currency::class)->findBy([], ['code' => 'ASC']);
            foreach ($all as $currency) {
                $label = sprintf('%s (%s)', $currency->getCode(), $currency->getName());
                $choices[$label] = (string) $currency->getCode();
            }
        }

        return $choices;
    }

    /**
     * Liste toutes les lignes de l'agence et calcule les statistiques globales.
     *
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     stats: array{total: int, active: int, suspended: int}
     * }
     */
    public function listAgencyRoutes(Agency $agency): array
    {
        /** @var list<Route> $routes */
        $routes = $this->em->getRepository(Route::class)->findBy(
            ['agency' => $agency],
            ['createdAt' => 'DESC']
        );

        $stats = [
            'total'     => \count($routes),
            'active'    => 0,
            'suspended' => 0,
        ];

        $lines = [];
        foreach ($routes as $route) {
            if ($route->isAvailable()) {
                ++$stats['active'];
            } else {
                ++$stats['suspended'];
            }

            /** @var list<RouteStop> $stops */
            $stops = $route->getRouteStops()->toArray();
            $departureCity = [] !== $stops ? (string) $stops[0]->getCity() : '-';
            $arrivalCity   = [] !== $stops ? (string) $stops[\count($stops) - 1]->getCity() : '-';

            // Récupération éventuelle du tarif principal (Départ -> Terminus) si configuré dans Fare
            $baseFareLabel = null;
            if (\count($stops) >= 2 && class_exists(Fare::class)) {
                $firstStop = $stops[0];
                $lastStop  = $stops[\count($stops) - 1];

                $fare = $this->em->createQueryBuilder()
                    ->select('f', 'c')
                    ->from(Fare::class, 'f')
                    ->leftJoin('f.currency', 'c')
                    ->andWhere('f.targetType = :targetType')
                    ->andWhere('f.targetId = :targetId')
                    ->andWhere('f.isActive = :isActive')
                    ->andWhere('f.departureStop = :dep')
                    ->andWhere('f.arrivalStop = :arr')
                    ->setParameter('targetType', FareTargetType::Route)
                    ->setParameter('targetId', $route->getId())
                    ->setParameter('isActive', true)
                    ->setParameter('dep', $firstStop)
                    ->setParameter('arr', $lastStop)
                    ->orderBy('f.validFrom', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($fare instanceof Fare) {
                    $baseFareLabel = sprintf(
                        '%s %s',
                        number_format((float) $fare->getAmount(), 0, ',', ' '),
                        $fare->getCurrency()?->getCode() ?? ''
                    );
                }
            }

            $lines[] = [
                'id'            => $route->getId(),
                'code'          => $route->getCode(),
                'name'          => $route->getName(),
                'slug'          => $route->getSlug(),
                'departureCity' => $departureCity,
                'arrivalCity'   => $arrivalCity,
                'stopsCount'    => \count($stops),
                'distance'      => (float) $route->getDistance(),
                'duration'      => $this->formatDuration((int) $route->getDuration()),
                'pricePerKg'    => null !== $route->getPricePerKg() ? (float) $route->getPricePerKg() : null,
                'currency'      => $route->getCurrencyPricePerKg()?->getCode() ?? 'CDF',
                'baseFareLabel' => $baseFareLabel,
                'isAvailable'   => $route->isAvailable(),
            ];
        }

        return [
            'lines' => $lines,
            'stats' => $stats,
        ];
    }

    public function findAgencyRouteBySlug(Agency $agency, string $slug): ?Route
    {
        return $this->em->getRepository(Route::class)->findOneBy([
            'agency' => $agency,
            'slug'   => $slug,
        ]);
    }

    /**
     * Crée une ligne de transport et l'ensemble de ses arrêts ordonnés (RouteStop).
     *
     * @throws \DomainException
     */
    public function createRoute(User $actor, RouteData $data): Route
    {
        $agency = $actor->getAgency() ?? throw new \LogicException('Agence introuvable.');

        $name = trim((string) $data->name);

        $rawCode = strtoupper(trim((string) $data->code));
        $code = '' !== $rawCode
            ? $rawCode
            : $this->generateUniqueRouteCode($agency, (string) $data->departureCity, (string) $data->terminusCity);

        if (null !== $this->em->getRepository(Route::class)->findOneBy(['agency' => $agency, 'code' => $code])) {
            throw new \DomainException(sprintf('Le code ligne [%s] existe déjà dans votre agence.', $code));
        }


        if (null !== $this->em->getRepository(Route::class)->findOneBy(['agency' => $agency, 'code' => $code])) {
            throw new \DomainException(sprintf('Le code ligne [%s] existe déjà dans votre agence.', $code));
        }

        $totalDistance = (float) $data->distance;
        $totalDurationMinutes = $this->parseDurationToMinutes((string) $data->duration);

        if ($totalDurationMinutes <= 0) {
            throw new \DomainException('La durée totale du trajet doit être supérieure à 0 minute.');
        }

        // Validation de la cohérence progressive des escales intermédiaires
        $previousDistance = 0.0;
        $previousDuration = 0;
        $normalizedStops = [];

        foreach ($data->stops as $index => $stopDto) {
            $stopDist = (float) $stopDto->distance;
            $stopDur  = $this->parseDurationToMinutes((string) $stopDto->duration);

            if ($stopDist <= $previousDistance || $stopDist >= $totalDistance) {
                throw new \DomainException(sprintf(
                    'L\'escale #%d (%s) doit avoir une distance comprise entre %s Km et %s Km.',
                    $index + 1,
                    $stopDto->city,
                    $previousDistance,
                    $totalDistance
                ));
            }

            if ($stopDur <= $previousDuration || $stopDur >= $totalDurationMinutes) {
                throw new \DomainException(sprintf(
                    'L\'escale #%d (%s) doit avoir une durée comprise entre %s et %s.',
                    $index + 1,
                    $stopDto->city,
                    $this->formatDuration($previousDuration),
                    $this->formatDuration($totalDurationMinutes)
                ));
            }

            $previousDistance = $stopDist;
            $previousDuration = $stopDur;

            $normalizedStops[] = [
                'city'     => trim((string) $stopDto->city),
                'country'  => trim((string) $stopDto->country),
                'distance' => $stopDist,
                'duration' => $stopDur,
            ];
        }

        $currency = $this->em->getRepository(Currency::class)->findOneBy(['code' => (string) $data->currencyCode]);
        if (!$currency instanceof Currency) {
            throw new \DomainException('La devise sélectionnée est invalide.');
        }

        $slug = $this->generateUniqueSlug($agency, $name);

        $route = new Route();

        $this->em->wrapInTransaction(function () use (
            $route,
            $agency,
            $actor,
            $name,
            $code,
            $slug,
            $totalDistance,
            $totalDurationMinutes,
            $data,
            $currency,
            $normalizedStops
        ): void {
            $route->setAgency($agency)
                ->setName($name)
                ->setCode($code)
                ->setSlug($slug)
                ->setDistance(sprintf('%.2f', $totalDistance))
                ->setDuration($totalDurationMinutes)
                ->setCreatedBy($actor)
                ->setIsAvailable($data->isAvailable)
                ->setReservationExpirationDelay($data->reservationExpirationDelay ?? 120)
                ->setPricePerKg(null !== $data->pricePerKg ? sprintf('%.2f', (float) $data->pricePerKg) : null)
                ->setCurrencyPricePerKg($currency);

            $this->em->persist($route);

            $order = 1;

            // 1. Arrêt de départ (Ordre 1)
            $departureStop = new RouteStop();
            $departureStop->setRoute($route)
                ->setStopOrder($order++)
                ->setCity(trim((string) $data->departureCity))
                ->setCountry(trim((string) $data->departureCountry))
                ->setDistanceFromDeparture('0.00')
                ->setDurationFromDeparture(0)
                ->setIsActive(true)
                ->setCreatedBy($actor);

            $this->em->persist($departureStop);
            $route->addRouteStop($departureStop);
            // 2. Escales intermédiaires
            foreach ($normalizedStops as $stopItem) {
                $midStop = new RouteStop();
                $midStop->setRoute($route)
                    ->setStopOrder($order++)
                    ->setCity($stopItem['city'])
                    ->setCountry($stopItem['country'])
                    ->setDistanceFromDeparture(sprintf('%.2f', $stopItem['distance']))
                    ->setDurationFromDeparture($stopItem['duration'])
                    ->setIsActive(true)
                    ->setCreatedBy($actor);
                $this->em->persist($midStop);
                $route->addRouteStop($midStop);
            }
            // 3. Arrêt terminus (Dernier ordre)
            $terminusStop = new RouteStop();
            $terminusStop->setRoute($route)
                ->setStopOrder($order)
                ->setCity(trim((string) $data->terminusCity))
                ->setCountry(trim((string) $data->terminusCountry))
                ->setDistanceFromDeparture(sprintf('%.2f', $totalDistance))
                ->setDurationFromDeparture($totalDurationMinutes)
                ->setIsActive(true)
                ->setCreatedBy($actor);
            $this->em->persist($terminusStop);
            $route->addRouteStop($terminusStop);
        });
        return $route;
    }
    /**
     * Rassemble toutes les données consolidées pour la fiche technique d'une ligne.
     *
     * @return array<string, mixed>
     */
    public function getRouteProfile(Route $route): array
    {
        $stopsData = [];
        foreach ($route->getRouteStops() as $stop) {
            $stopsData[] = [
                'id'                    => $stop->getId(),
                'stopOrder'             => $stop->getStopOrder(),
                'city'                  => $stop->getCity(),
                'country'               => $stop->getCountry(),
                'distanceFromDeparture' => (float) $stop->getDistanceFromDeparture(),
                'durationFromDeparture' => $this->formatDuration((int) $stop->getDurationFromDeparture()),
                'isActive'              => $stop->isActive(),
            ];
        }
        // Extraction des tarifs configurés sur cette ligne (polymorphisme FareTargetType::Route)
        $faresData = [];
        if (class_exists(Fare::class)) {
            $rawFares = $this->em->createQueryBuilder()
                ->select('f', 'dep', 'arr', 'c')
                ->from(Fare::class, 'f')
                ->leftJoin('f.departureStop', 'dep')
                ->leftJoin('f.arrivalStop', 'arr')
                ->leftJoin('f.currency', 'c')
                ->andWhere('f.targetType = :targetType')
                ->andWhere('f.targetId = :targetId')
                ->setParameter('targetType', FareTargetType::Route)
                ->setParameter('targetId', $route->getId())
                ->orderBy('dep.stopOrder', 'ASC')
                ->addOrderBy('arr.stopOrder', 'ASC')
                ->getQuery()
                ->getResult();
            foreach ($rawFares as $fare) {
                if ($fare instanceof Fare) {
                    $faresData[] = [
                        'id'            => $fare->getId(),
                        'departureCity' => $fare->getDepartureStop()?->getCity() ?? 'Toute la ligne',
                        'arrivalCity'   => $fare->getArrivalStop()?->getCity() ?? '-',
                        'fareType'      => $fare->getFareType()->value,
                        'amount'        => (float) $fare->getAmount(),
                        'pricePerKg'    => null !== $fare->getPricePerKg() ? (float) $fare->getPricePerKg() : null,
                        'currency'      => $fare->getCurrency()?->getCode() ?? 'CDF',
                        'isActive'      => $fare->isActive(),
                        'validFrom'     => $fare->getValidFrom(),
                        'validUntil'    => $fare->getValidUntil(),
                    ];
                }
            }
        }
        return [
            'route' => [
                'id'                         => $route->getId(),
                'name'                       => $route->getName(),
                'code'                       => $route->getCode(),
                'slug'                       => $route->getSlug(),
                'distance'                   => (float) $route->getDistance(),
                'duration'                   => $this->formatDuration((int) $route->getDuration()),
                'pricePerKg'                 => null !== $route->getPricePerKg() ? (float) $route->getPricePerKg() : 0.0,
                'currency'                   => $route->getCurrencyPricePerKg()?->getCode() ?? 'CDF',
                'isAvailable'                => $route->isAvailable(),
                'reservationExpirationDelay' => $route->getReservationExpirationDelay() ?? 120,
                'createdAt'                  => $route->getCreatedAt(),
                'createdBy'                  => $route->getCreatedBy()?->getFullName() ?? 'Système',
                'lastUpdatedAt'              => $route->getLastUpdatedAt(),
                'stops'                      => $stopsData,
                'fares'                      => $faresData,
            ],
        ];
    }
    /**
     * Active ou suspend l'exploitation d'une ligne entière.
     */
    public function toggleRouteAvailability(Route $route): bool
    {
        $newState = !$route->isAvailable();
        $route->setIsAvailable($newState);
        $this->em->flush();
        return $newState;
    }
    /**
     * Active ou suspend une escale intermédiaire (les arrêts départ et terminus restent obligatoirement actifs).
     *
     * @throws \DomainException
     */
    public function toggleStopAvailability(Route $route, int $stopId): bool
    {
        $stops = $route->getRouteStops()->toArray();
        $totalStops = \count($stops);
        $targetStop = null;
        foreach ($stops as $index => $stop) {
            if ($stop->getId() === $stopId) {
                if (0 === $index || ($totalStops - 1) === $index) {
                    throw new \DomainException('Impossible de suspendre la gare de départ ou le terminus de la ligne.');
                }
                $targetStop = $stop;
                break;
            }
        }
        if (!$targetStop instanceof RouteStop) {
            throw new \DomainException('Escale introuvable sur cette ligne.');
        }
        $newState = !$targetStop->isActive();
        $targetStop->setIsActive($newState);
        $this->em->flush();
        return $newState;
    }
    /**
     * Convertit une chaîne (ex: "11h30" ou "690") en nombre total de minutes.
     */
    public function parseDurationToMinutes(string $raw): int
    {
        $clean = strtolower(trim($raw));
        if (str_contains($clean, 'h')) {
            [$hours, $mins] = explode('h', $clean, 2);
            return ((int) $hours * 60) + (int) $mins;
        }
        return (int) $clean;
    }
    /**
     * Formate un nombre de minutes en chaîne lisible (ex: 690 -> "11h30").
     */
    public function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;
        return sprintf('%dh%02d', $hours, $mins);
    }

    private function generateUniqueSlug(Agency $agency, string $name): string
    {
        $slugger = new AsciiSlugger('fr');
        $baseSlug = strtolower($slugger->slug($name)->toString());
        if ('' === $baseSlug) {
            $baseSlug = 'ligne';
        }
        $slug = $baseSlug;
        $counter = 2;
        while (null !== $this->findAgencyRouteBySlug($agency, $slug)) {
            $slug = $baseSlug . '-' . $counter;
            ++$counter;
        }
        return $slug;
    }

    private function generateUniqueRouteCode(Agency $agency, string $departureCity, string $terminusCity): string
    {
        $slugger = new AsciiSlugger('fr');
        $depPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $slugger->slug($departureCity)->toString()) ?: 'DEP', 0, 3));
        $arrPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $slugger->slug($terminusCity)->toString()) ?: 'ARR', 0, 3));

        $baseCode = sprintf('%s-%s', str_pad($depPrefix, 3, 'X'), str_pad($arrPrefix, 3, 'X'));
        $repo = $this->em->getRepository(Route::class);

        if (null === $repo->findOneBy(['agency' => $agency, 'code' => $baseCode])) {
            return $baseCode;
        }

        for ($i = 2; $i < 500; ++$i) {
            $candidate = sprintf('%s-%d', $baseCode, $i);
            if (null === $repo->findOneBy(['agency' => $agency, 'code' => $candidate])) {
                return $candidate;
            }
        }

        return $baseCode . '-' . strtoupper(bin2hex(random_bytes(2)));
    }
}
