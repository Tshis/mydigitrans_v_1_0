<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agency>
 */
class AgencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agency::class);
    }

    public function findOneBySlug(string $slug): ?Agency
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findOneByCode(string $code): ?Agency
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @return Agency[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Agences parrainées par un utilisateur (partenaire / apporteur d'affaires).
     *
     * @return Agency[]
     */
    public function findByParent(User $parent): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Agences venues sans parrain.
     *
     * @return Agency[]
     */
    public function findWithoutParent(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.parent IS NULL')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
