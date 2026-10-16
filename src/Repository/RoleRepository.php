<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use App\Enum\Scope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findOneByCode(string $code): ?Role
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @return Role[]
     */
    public function findActiveByScope(Scope $scope): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.scope = :scope')
            ->andWhere('r.isActive = :active')
            ->setParameter('scope', $scope)
            ->setParameter('active', true)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
