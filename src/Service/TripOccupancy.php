<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TripSeat;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Remplissage des voyages : capacité (sièges vendables) et places prises
 * (réservées, ou retenues dont le délai court encore).
 */
final class TripOccupancy
{
    private const SELLABLE_SEAT_TYPES = [SeatType::Normal, SeatType::Vip, SeatType::Extra];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param list<int> $tripIds
     *
     * @return array<int, array{capacity: int, taken: int}> indexé par id de voyage
     */
    public function forTrips(array $tripIds, \DateTimeImmutable $now): array
    {
        if ([] === $tripIds) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select(
                'IDENTITY(ts.trip) AS tripId',
                'COUNT(ts.id) AS capacity',
                'SUM(CASE WHEN ts.seatStatus = :reserved THEN 1 WHEN (ts.seatStatus = :held AND ts.heldUntil >= :now) THEN 1 ELSE 0 END) AS taken',
            )
            ->from(TripSeat::class, 'ts')
            ->join('ts.seat', 's')
            ->andWhere('IDENTITY(ts.trip) IN (:tripIds)')
            ->andWhere('s.type IN (:types)')
            ->setParameter('tripIds', $tripIds)
            ->setParameter('types', array_map(static fn (SeatType $t): string => $t->value, self::SELLABLE_SEAT_TYPES))
            ->setParameter('reserved', TripSeatStatus::Reserved->value)
            ->setParameter('held', TripSeatStatus::Held->value)
            ->setParameter('now', $now)
            ->groupBy('ts.trip')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['tripId']] = ['capacity' => (int) $row['capacity'], 'taken' => (int) $row['taken']];
        }

        return $counts;
    }
}
