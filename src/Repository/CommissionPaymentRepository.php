<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommissionPayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommissionPayment>
 */
class CommissionPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommissionPayment::class);
    }

    public function findOneByReference(string $value): ?CommissionPayment
    {
        return $this->findOneBy(['reference' => $value]);
    }
}
