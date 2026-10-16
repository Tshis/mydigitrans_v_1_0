<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlatformDiscount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformDiscount>
 */
class PlatformDiscountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformDiscount::class);
    }
}
