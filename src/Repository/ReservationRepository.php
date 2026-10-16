<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findOneByReference(string $value): ?Reservation
    {
        return $this->findOneBy(['reference' => $value]);
    }

    /**
     * Réservations en attente dont le délai d'expiration est dépassé.
     *
     * @return Reservation[]
     */
    public function findExpiredPending(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresAt IS NOT NULL')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('status', ReservationStatus::Pending)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
