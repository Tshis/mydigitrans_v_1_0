<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\FareMatrixData;
use App\Dto\admin\agency\FareSegmentData;
use App\Entity\Agency;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\Fare;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Enum\FareTargetType;
use App\Enum\FareType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion de la matrice tarifaire par ligne et par tronçon (Fare).
 */
final class FareService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Retourne toutes les lignes de l'agence pour le Master Selector.
     *
     * @return list<Route>
     */
    public function getAgencyRoutes(Agency $agency): array
    {
        return $this->em->getRepository(Route::class)->findBy(
            ['agency' => $agency],
            ['name' => 'ASC']
        );
    }

    public function findAgencyRouteBySlug(Agency $agency, ?string $slug): ?Route
    {
        if (null !== $slug && '' !== trim($slug)) {
            $route = $this->em->getRepository(Route::class)->findOneBy([
                'agency' => $agency,
                'slug'   => trim($slug),
            ]);
            if ($route instanceof Route) {
                return $route;
            }
        }

        return $this->em->getRepository(Route::class)->findOneBy(
            ['agency' => $agency],
            ['name' => 'ASC']
        );
    }

    /**
     * Retourne les devises actives de l'agence pour le ChoiceType.
     *
     * @return array<string, string>
     */
    public function getAgencyCurrencyChoices(Agency $agency): array
    {
        $relations = $this->em->getRepository(CurrencyAgency::class)->findBy([
            'agency'   => $agency,
            'isActive' => true,
        ], ['isBase' => 'DESC']);

        $choices = [];
        foreach ($relations as $rel) {
            $currency = $rel->getCurrency();
            if ($currency instanceof Currency) {
                $code = (string) $currency->getCode();
                $choices[$code] = $code;
            }
        }

        if ([] === $choices) {
            $all = $this->em->getRepository(Currency::class)->findBy([], ['code' => 'ASC']);
            foreach ($all as $currency) {
                $code = (string) $currency->getCode();
                $choices[$code] = $code;
            }
        }

        return $choices;
    }

    /**
     * Résout le code de la devise de base de l'agence (isBase = true).
     */
    public function getAgencyBaseCurrencyCode(Agency $agency, ?Route $fallbackRoute = null): string
    {
        $baseRelation = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency'   => $agency,
            'isBase'   => true,
            'isActive' => true,
        ]);

        if ($baseRelation instanceof CurrencyAgency && null !== $baseRelation->getCurrency()) {
            return (string) $baseRelation->getCurrency()->getCode();
        }

        if (null !== $fallbackRoute?->getCurrencyPricePerKg()) {
            return (string) $fallbackRoute->getCurrencyPricePerKg()->getCode();
        }

        $firstActive = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency'   => $agency,
            'isActive' => true,
        ]);

        if ($firstActive instanceof CurrencyAgency && null !== $firstActive->getCurrency()) {
            return (string) $firstActive->getCurrency()->getCode();
        }

        return 'CDF';
    }

    /**
     * Construit le DTO FareMatrixData pré-rempli avec la devise de base de l'agence et tous les segments possibles.
     */
    public function buildMatrixDto(Route $route, FareType $fareType = FareType::Standard): FareMatrixData
    {
        $agency = $route->getAgency();
        $defaultCurrencyCode = null !== $agency
            ? $this->getAgencyBaseCurrencyCode($agency, $route)
            : ($route->getCurrencyPricePerKg()?->getCode() ?? 'CDF');

        $dto = new FareMatrixData();
        $dto->fareType     = $fareType;
        $dto->currencyCode = $defaultCurrencyCode;
        $dto->directFret   = null !== $route->getPricePerKg() ? (float) $route->getPricePerKg() : 0.0;

        /** @var list<RouteStop> $stops */
        $stops = $route->getRouteStops()->toArray();
        $count = \count($stops);
        if ($count < 2) {
            return $dto;
        }

        // Indexation des tarifs actifs existants pour cette ligne et cette catégorie (Standard / VIP)
        /** @var list<Fare> $existingFares */
        $existingFares = $this->em->createQueryBuilder()
            ->select('f', 'dep', 'arr', 'c')
            ->from(Fare::class, 'f')
            ->leftJoin('f.departureStop', 'dep')
            ->leftJoin('f.arrivalStop', 'arr')
            ->leftJoin('f.currency', 'c')
            ->andWhere('f.targetType = :targetType')
            ->andWhere('f.targetId = :targetId')
            ->andWhere('f.fareType = :fareType')
            ->andWhere('f.isActive = :isActive')
            ->setParameter('targetType', FareTargetType::Route)
            ->setParameter('targetId', $route->getId())
            ->setParameter('fareType', $fareType)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();

        $fareMap = [];
        foreach ($existingFares as $fare) {
            $depId = $fare->getDepartureStop()?->getId();
            $arrId = $fare->getArrivalStop()?->getId();
            if (null !== $depId && null !== $arrId) {
                $fareMap[$depId . '_' . $arrId] = $fare;
            }
        }

        $firstStop = $stops;
        $lastStop  = $stops[$count - 1];
        $directKey = $firstStop[0]->getId() . '_' . $lastStop->getId();

        if (isset($fareMap[$directKey])) {
            $directFare = $fareMap[$directKey];
            $dto->directAmount = (float) $directFare->getAmount();
            $dto->directFret   = null !== $directFare->getPricePerKg() ? (float) $directFare->getPricePerKg() : $dto->directFret;
            $dto->currencyCode = $directFare->getCurrency()?->getCode() ?? $defaultCurrencyCode;
        }

        // Génération de toutes les paires de tronçons intermédiaires (i < j), hors trajet direct (0 -> count-1)
        $segments = [];
        for ($i = 0; $i < $count - 1; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                if (0 === $i && ($count - 1) === $j) {
                    continue;
                }

                $fromStop = $stops[$i];
                $toStop   = $stops[$j];
                $key      = $fromStop->getId() . '_' . $toStop->getId();

                $seg = new FareSegmentData();
                $seg->fromStopId = $fromStop->getId();
                $seg->toStopId   = $toStop->getId();
                $seg->fromCity   = (string) $fromStop->getCity();
                $seg->toCity     = (string) $toStop->getCity();
                $seg->distance   = max(0.0, (float) $toStop->getDistanceFromDeparture() - (float) $fromStop->getDistanceFromDeparture());

                if (isset($fareMap[$key])) {
                    $existing = $fareMap[$key];
                    $seg->amount     = (float) $existing->getAmount();
                    $seg->pricePerKg = null !== $existing->getPricePerKg() ? (float) $existing->getPricePerKg() : null;
                    $seg->isActive   = $existing->isActive();
                } else {
                    $seg->amount     = null;
                    $seg->pricePerKg = null;
                    $seg->isActive   = true;
                }

                $segments[] = $seg;
            }
        }

        $dto->segments = $segments;

        return $dto;
    }

    /**
     * Enregistre en transaction la grille tarifaire complète (direct + segments) pour une route.
     *
     * @throws \DomainException
     */
    public function saveMatrix(User $actor, Route $route, FareMatrixData $data): int
    {
        $agency = $actor->getAgency() ?? throw new \LogicException('Agence introuvable.');

        $currency = $this->em->getRepository(Currency::class)->findOneBy(['code' => (string) $data->currencyCode]);
        if (!$currency instanceof Currency) {
            throw new \DomainException('La devise sélectionnée est invalide.');
        }

        /** @var list<RouteStop> $stops */
        $stops = $route->getRouteStops()->toArray();
        if (\count($stops) < 2) {
            throw new \DomainException('Cette ligne doit comporter au moins un arrêt de départ et un terminus.');
        }

        $stopsById = [];
        foreach ($stops as $stop) {
            $stopsById[(int) $stop->getId()] = $stop;
        }

        $firstStop = $stops;
        $lastStop  = $stops[\count($stops) - 1];
        $now       = new \DateTimeImmutable();

        $savedCount = 0;

        $this->em->wrapInTransaction(function () use (
            $agency,
            $actor,
            $route,
            $data,
            $currency,
            $stopsById,
            $firstStop,
            $lastStop,
            $now,
            &$savedCount
        ): void {
            // 1. Désactivation des anciens tarifs actifs pour cette ligne et cette catégorie
            /** @var list<Fare> $oldFares */
            $oldFares = $this->em->createQueryBuilder()
                ->select('f')
                ->from(Fare::class, 'f')
                ->andWhere('f.targetType = :targetType')
                ->andWhere('f.targetId = :targetId')
                ->andWhere('f.fareType = :fareType')
                ->andWhere('f.isActive = :isActive')
                ->setParameter('targetType', FareTargetType::Route)
                ->setParameter('targetId', $route->getId())
                ->setParameter('fareType', $data->fareType)
                ->setParameter('isActive', true)
                ->getQuery()

                ->getResult();
            foreach ($oldFares as $old) {
                $old->setIsActive(false)
                    ->setValidUntil($now);
            }
            // 2. Création du tarif direct (Départ -> Terminus)
            $directFare = new Fare();
            $directFare->setAgency($agency)
                ->setFareType($data->fareType)
                ->setTargetType(FareTargetType::Route)
                ->setTargetId((int) $route->getId())
                ->setDepartureStop($firstStop[0])
                ->setArrivalStop($lastStop)
                ->setAmount(sprintf('%.2f', (float) $data->directAmount))
                ->setPricePerKg(null !== $data->directFret ? sprintf('%.2f', (float) $data->directFret) : null)
                ->setCurrency($currency)
                ->setIsActive(true)
                ->setValidFrom($now)
                ->setCreatedBy($actor);
            $this->em->persist($directFare);
            ++$savedCount;
            // 3. Création des tarifs des tronçons intermédiaires renseignés
            foreach ($data->segments as $seg) {
                if (null === $seg->amount || $seg->amount <= 0) {
                    continue;
                }
                $fromStop = $stopsById[(int) $seg->fromStopId] ?? null;
                $toStop   = $stopsById[(int) $seg->toStopId] ?? null;
                if (!$fromStop instanceof RouteStop || !$toStop instanceof RouteStop) {
                    continue;
                }
                $segFare = new Fare();
                $segFare->setAgency($agency)
                    ->setFareType($data->fareType)
                    ->setTargetType(FareTargetType::Route)
                    ->setTargetId((int) $route->getId())
                    ->setDepartureStop($fromStop)
                    ->setArrivalStop($toStop)
                    ->setAmount(sprintf('%.2f', (float) $seg->amount))
                    ->setPricePerKg(null !== $seg->pricePerKg ? sprintf('%.2f', (float) $seg->pricePerKg) : null)
                    ->setCurrency($currency)
                    ->setIsActive($seg->isActive)
                    ->setValidFrom($now)
                    ->setCreatedBy($actor);
                $this->em->persist($segFare);
                ++$savedCount;
            }
        });
        return $savedCount;
    }
    /**
     * Retourne les métadonnées résumées de la route pour l'en-tête Twig.
     *
     * @return array<string, mixed>
     */
    public function getRouteSummary(Route $route): array
    {
        $agency = $route->getAgency();
        $stops = $route->getRouteStops()->toArray();
        $startCity = [] !== $stops ? (string) $stops[0]->getCity() : '-';
        $endCity   = [] !== $stops ? (string) $stops[\count($stops) - 1]->getCity() : '-';
        return [
            'id'         => $route->getId(),
            'name'       => $route->getName(),
            'code'       => $route->getCode(),
            'slug'       => $route->getSlug(),
            'start_city' => $startCity,
            'end_city'   => $endCity,
            'distance'   => (float) $route->getDistance(),
            'currency'   => null !== $agency
                ? $this->getAgencyBaseCurrencyCode($agency, $route)
                : ($route->getCurrencyPricePerKg()?->getCode() ?? 'CDF'),
        ];
    }
}
