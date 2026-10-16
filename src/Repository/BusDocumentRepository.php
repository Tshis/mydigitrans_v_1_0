<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BusDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusDocument>
 */
class BusDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusDocument::class);
    }

    public function findOneByCode(string $value): ?BusDocument
    {
        return $this->findOneBy(['code' => $value]);
    }

    /**
     * Documents expirant au plus tard à la date donnée.
     *
     * @return BusDocument[]
     */
    public function findExpiringBefore(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('bd')
            ->andWhere('bd.expiredAt <= :date')
            ->setParameter('date', $date)
            ->orderBy('bd.expiredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
