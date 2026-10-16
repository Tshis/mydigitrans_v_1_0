<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlatformFinancialOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformFinancialOperation>
 */
class PlatformFinancialOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformFinancialOperation::class);
    }
}
