<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BusLayoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BusLayoutRepository::class)]
#[ORM\Table(name: 'bus_layout')]
class BusLayout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'row_count')]
    private ?int $rows = null;

    #[ORM\Column(name: 'column_count')]
    private ?int $columns = null;

    #[ORM\Column(type: Types::JSON)]
    private array $aisles = [];

    #[ORM\Column(type: Types::JSON)]
    private array $specialPositions = [];

    #[ORM\Column]
    private bool $hasBackExtraSeat = false;

    #[ORM\Column]
    private bool $isValidated = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(?Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    public function getRows(): ?int
    {
        return $this->rows;
    }

    public function setRows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function getColumns(): ?int
    {
        return $this->columns;
    }

    public function setColumns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getAisles(): array
    {
        return $this->aisles;
    }

    public function setAisles(array $aisles): static
    {
        $this->aisles = $aisles;

        return $this;
    }

    public function getSpecialPositions(): array
    {
        return $this->specialPositions;
    }

    public function setSpecialPositions(array $specialPositions): static
    {
        $this->specialPositions = $specialPositions;

        return $this;
    }

    public function hasBackExtraSeat(): bool
    {
        return $this->hasBackExtraSeat;
    }

    public function setHasBackExtraSeat(bool $hasBackExtraSeat): static
    {
        $this->hasBackExtraSeat = $hasBackExtraSeat;

        return $this;
    }

    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $isValidated): static
    {
        $this->isValidated = $isValidated;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }
}
