<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BusLocationHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusLocationHistory>
 */
class BusLocationHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusLocationHistory::class);
    }
}
