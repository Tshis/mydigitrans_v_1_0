<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommissionPaymentItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommissionPaymentItem>
 */
class CommissionPaymentItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommissionPaymentItem::class);
    }
}
