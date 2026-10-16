<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SeatType;
use App\Repository\SeatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeatRepository::class)]
#[ORM\Table(name: 'seat')]
#[ORM\UniqueConstraint(columns: ['bus_id', 'seat_label'])]
class Seat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Bus::class, inversedBy: 'seats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Bus $bus = null;

    #[ORM\Column(length: 20)]
    private ?string $seatLabel = null;

    #[ORM\Column(enumType: SeatType::class, length: 30)]
    private SeatType $type = SeatType::Normal;

    #[ORM\Column(name: 'seat_row')]
    private ?int $row = null;

    #[ORM\Column(name: 'seat_column')]
    private ?int $column = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function setBus(Bus $bus): static
    {
        $this->bus = $bus;

        return $this;
    }

    public function getSeatLabel(): ?string
    {
        return $this->seatLabel;
    }

    public function setSeatLabel(string $seatLabel): static
    {
        $this->seatLabel = $seatLabel;

        return $this;
    }

    public function getType(): SeatType
    {
        return $this->type;
    }

    public function setType(SeatType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getRow(): ?int
    {
        return $this->row;
    }

    public function setRow(int $row): static
    {
        $this->row = $row;

        return $this;
    }

    public function getColumn(): ?int
    {
        return $this->column;
    }

    public function setColumn(int $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
