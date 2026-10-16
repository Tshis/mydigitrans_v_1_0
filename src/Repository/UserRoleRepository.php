<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRole>
 */
class UserRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRole::class);
    }

    /**
     * @return UserRole[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('ur')
            ->andWhere('ur.user = :user')
            ->andWhere('ur.revokedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
