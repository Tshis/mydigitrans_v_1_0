<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\Subscription;
use App\Enum\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * Abonnement actif d'une agence à la date donnée.
     */
    public function findActiveForAgency(Agency $agency, \DateTimeImmutable $at): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.agency = :agency')
            ->andWhere('s.status = :status')
            ->andWhere('s.startAt <= :at')
            ->andWhere('s.endAt >= :at')
            ->setParameter('agency', $agency)
            ->setParameter('status', SubscriptionStatus::Active)
            ->setParameter('at', $at)
            ->orderBy('s.endAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
