<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TripSeat;
use App\Enum\TripSeatStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TripSeat>
 */
class TripSeatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TripSeat::class);
    }

    /**
     * Sièges en attente dont le délai de blocage est dépassé.
     *
     * @return TripSeat[]
     */
    public function findExpiredHolds(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('ts')
            ->andWhere('ts.seatStatus = :status')
            ->andWhere('ts.heldUntil IS NOT NULL')
            ->andWhere('ts.heldUntil < :now')
            ->setParameter('status', TripSeatStatus::Held)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
