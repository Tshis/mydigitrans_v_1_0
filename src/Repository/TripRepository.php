<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    public function findOneByCode(string $value): ?Trip
    {
        return $this->findOneBy(['code' => $value]);
    }

    public function findOneBySlug(string $value): ?Trip
    {
        return $this->findOneBy(['slug' => $value]);
    }

    /**
     * Récupère un voyage avec son bus et son layout en une seule requête SQL.
     */
    public function findOneBySlugWithLayout(string $slug): ?Trip
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'b', 'bl', 'r', 'a') // On force la sélection de toutes les entités liées
            ->join('t.bus', 'b')
            ->join('b.busLayout', 'bl')
            ->join('t.route', 'r')
            ->join('r.agency', 'a')
            ->andWhere('t.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    } //findOneBySlugWithLayout
}
