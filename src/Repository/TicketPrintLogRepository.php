<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TicketPrintLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketPrintLog>
 */
class TicketPrintLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketPrintLog::class);
    }
}
