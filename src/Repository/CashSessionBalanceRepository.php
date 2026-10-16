<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CashSessionBalance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashSessionBalance>
 */
class CashSessionBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashSessionBalance::class);
    }
}
