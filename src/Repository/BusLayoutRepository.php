<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BusLayout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusLayout>
 */
class BusLayoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusLayout::class);
    }

    public function findOneByCode(string $value): ?BusLayout
    {
        return $this->findOneBy(['code' => $value]);
    }
}
