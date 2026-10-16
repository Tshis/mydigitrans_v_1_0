<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Enum\BranchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Branch>
 */
class BranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Branch::class);
    }

    public function findOneByAgencyAndCode(Agency $agency, string $code): ?Branch
    {
        return $this->findOneBy(['agency' => $agency, 'code' => $code]);
    }

    public function findOneByAgencyAndSlug(Agency $agency, string $slug): ?Branch
    {
        return $this->findOneBy(['agency' => $agency, 'slug' => $slug]);
    }

    /**
     * @return Branch[]
     */
    public function findByAgency(Agency $agency, ?BranchStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('b.name', 'ASC');

        if (null !== $status) {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByAgency(Agency $agency): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.agency = :agency')
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
