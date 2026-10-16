<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fare;
use App\Enum\FareTargetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fare>
 */
class FareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fare::class);
    }

    /**
     * Tarifs actifs pour une cible (route, forfait, fonctionnalité) à une date donnée.
     *
     * @return Fare[]
     */
    public function findActiveByTarget(FareTargetType $type, int $targetId, \DateTimeImmutable $at): array
    {
        $qb = $this->createQueryBuilder('f');

        return $qb
            ->andWhere('f.targetType = :type')
            ->andWhere('f.targetId = :targetId')
            ->andWhere('f.isActive = :active')
            ->andWhere('f.validFrom <= :at')
            ->andWhere($qb->expr()->orX('f.validUntil IS NULL', 'f.validUntil >= :at'))
            ->setParameter('type', $type)
            ->setParameter('targetId', $targetId)
            ->setParameter('active', true)
            ->setParameter('at', $at)
            ->getQuery()
            ->getResult();
    }
}
