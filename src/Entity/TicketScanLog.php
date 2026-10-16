<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ScanResult;
use App\Repository\TicketScanLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketScanLogRepository::class)]
#[ORM\Table(name: 'ticket_scan_log')]
#[ORM\Index(columns: ['ticket_number'])]
class TicketScanLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $ticketNumber = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $scannedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $scannedAt = null;

    #[ORM\Column(enumType: ScanResult::class, length: 30)]
    private ScanResult $result = ScanResult::Valid;

    public function __construct()
    {
        $this->scannedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketNumber(): ?string
    {
        return $this->ticketNumber;
    }

    public function setTicketNumber(string $ticketNumber): static
    {
        $this->ticketNumber = $ticketNumber;

        return $this;
    }

    public function getScannedBy(): ?User
    {
        return $this->scannedBy;
    }

    public function setScannedBy(User $scannedBy): static
    {
        $this->scannedBy = $scannedBy;

        return $this;
    }

    public function getScannedAt(): ?\DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function getResult(): ScanResult
    {
        return $this->result;
    }

    public function setResult(ScanResult $result): static
    {
        $this->result = $result;

        return $this;
    }
}
