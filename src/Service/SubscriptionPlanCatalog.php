<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlanFeature;
use App\Entity\SubscriptionPlan;
use App\Enum\ActiveStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Plans d'abonnement actifs, prêts pour la page « Sélectionnez votre Plan d'Abonnement ».
 */
final class SubscriptionPlanCatalog
{
    private const UNLIMITED = 'Illimité';

    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @return list<array{id: int, level: string, name: string, price: string, currency: string, duration_days: int, max_branches: int|string, max_buses: int|string, max_users: int|string, features: array<string, string>}>
     */
    public function activePlans(): array
    {
        /** @var list<SubscriptionPlan> $plans */
        $plans = $this->em->createQueryBuilder()
            ->select('p', 'c')
            ->from(SubscriptionPlan::class, 'p')
            ->join('p.currency', 'c')
            ->andWhere('p.status = :active')
            ->setParameter('active', ActiveStatus::Active->value)
            ->orderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();

        if ([] === $plans) {
            return [];
        }

        /** @var list<PlanFeature> $links */
        $links = $this->em->createQueryBuilder()
            ->select('pf', 'f')
            ->from(PlanFeature::class, 'pf')
            ->join('pf.feature', 'f')
            ->andWhere('IDENTITY(pf.plan) IN (:plans)')
            ->andWhere('f.status = :active')
            ->setParameter('plans', array_map(static fn(SubscriptionPlan $p): int => (int) $p->getId(), $plans))
            ->setParameter('active', ActiveStatus::Active->value)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        $features = [];
        foreach ($links as $link) {
            $feature = $link->getFeature();
            $features[(int) $link->getPlan()?->getId()][(string) $feature?->getCode()] = (string) $feature?->getName();
        }

        $out = [];
        foreach ($plans as $plan) {
            $days = (int) $plan->getDurationDays();

            $out[] = [
                'id' => (int) $plan->getId(),
                'level' => match (true) {
                    $days <= 15 => 'Essai',
                    $days <= 31 => 'Mensuel',
                    $days >= 360 => 'Annuel',
                    default => 'Périodique',
                },
                'name' => (string) $plan->getName(),
                'price' => (string) $plan->getPrice(),
                'currency' => (string) $plan->getCurrency()?->getCode(),
                'duration_days' => $days,
                'max_branches' => $plan->getMaxBranches() ?? self::UNLIMITED,
                'max_buses' => $plan->getMaxBuses() ?? self::UNLIMITED,
                'max_users' => $plan->getMaxUsers() ?? self::UNLIMITED,
                'features' => $features[(int) $plan->getId()] ?? [],
            ];
        }

        return $out;
    }
}
