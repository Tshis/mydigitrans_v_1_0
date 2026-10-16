<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PrintReason;
use App\Repository\TicketPrintLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketPrintLogRepository::class)]
#[ORM\Table(name: 'ticket_print_log')]
#[ORM\Index(columns: ['ticket_id'])]
class TicketPrintLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ticket::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ticket $ticket = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $printedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $printedAt = null;

    #[ORM\Column(enumType: PrintReason::class, length: 30)]
    private PrintReason $reason = PrintReason::Original;

    #[ORM\Column]
    private int $printCount = 1;

    public function __construct()
    {
        $this->printedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(Ticket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getPrintedBy(): ?User
    {
        return $this->printedBy;
    }

    public function setPrintedBy(User $printedBy): static
    {
        $this->printedBy = $printedBy;

        return $this;
    }

    public function getPrintedAt(): ?\DateTimeImmutable
    {
        return $this->printedAt;
    }

    public function getReason(): PrintReason
    {
        return $this->reason;
    }

    public function setReason(PrintReason $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getPrintCount(): int
    {
        return $this->printCount;
    }

    public function setPrintCount(int $printCount): static
    {
        $this->printCount = $printCount;

        return $this;
    }
}
