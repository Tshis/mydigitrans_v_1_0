<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findOneByInvoiceNumber(string $value): ?Invoice
    {
        return $this->findOneBy(['invoiceNumber' => $value]);
    }

    /**
     * Factures émises dont l'échéance est dépassée.
     *
     * @return Invoice[]
     */
    public function findOverdue(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.dueDate IS NOT NULL')
            ->andWhere('i.dueDate < :now')
            ->setParameter('status', InvoiceStatus::Issued)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
