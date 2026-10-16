<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\Currency;
use App\Entity\ExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeRate>
 */
class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    /**
     * Dernier taux applicable à la date donnée. Le taux propre à l'agence est
     * prioritaire sur le taux global (agency IS NULL).
     */
    public function findLatest(Currency $base, Currency $target, ?Agency $agency, \DateTimeImmutable $at): ?ExchangeRate
    {
        $qb = $this->createQueryBuilder('er')
            ->andWhere('er.baseCurrency = :base')
            ->andWhere('er.targetCurrency = :target')
            ->andWhere('er.effectiveFrom <= :at')
            ->setParameter('base', $base)
            ->setParameter('target', $target)
            ->setParameter('at', $at);

        if (null === $agency) {
            $qb->andWhere('er.agency IS NULL');
        } else {
            $qb->andWhere($qb->expr()->orX('er.agency = :agency', 'er.agency IS NULL'))
                ->setParameter('agency', $agency)
                ->addSelect('CASE WHEN er.agency IS NULL THEN 1 ELSE 0 END AS HIDDEN isGlobal')
                ->orderBy('isGlobal', 'ASC');
        }

        return $qb->addOrderBy('er.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
