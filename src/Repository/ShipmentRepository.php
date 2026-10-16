<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shipment>
 */
class ShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shipment::class);
    }

    public function findOneByReference(string $value): ?Shipment
    {
        return $this->findOneBy(['reference' => $value]);
    }

    public function findOneByPickupCode(string $value): ?Shipment
    {
        return $this->findOneBy(['pickupCode' => $value]);
    }
}
