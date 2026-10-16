<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TicketScanLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketScanLog>
 */
class TicketScanLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketScanLog::class);
    }
}
