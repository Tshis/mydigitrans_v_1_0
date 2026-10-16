<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FeeSourceType;
use App\Enum\FeeType;
use App\Repository\PlatformFeeRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformFeeRuleRepository::class)]
#[ORM\Table(name: 'platform_fee_rule')]
#[ORM\Index(columns: ['source_type', 'is_active'])]
class PlatformFeeRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(enumType: FeeSourceType::class, length: 30)]
    private FeeSourceType $sourceType = FeeSourceType::Reservation;

    #[ORM\Column(enumType: FeeType::class, length: 30)]
    private FeeType $feeType = FeeType::Percent;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private ?string $value = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Currency $currency = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Agency $agency = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private bool $isActive = true;

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

    public function getSourceType(): FeeSourceType
    {
        return $this->sourceType;
    }

    public function setSourceType(FeeSourceType $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getFeeType(): FeeType
    {
        return $this->feeType;
    }

    public function setFeeType(FeeType $feeType): static
    {
        $this->feeType = $feeType;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
