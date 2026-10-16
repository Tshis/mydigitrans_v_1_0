<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FareTargetType;
use App\Enum\FareType;
use App\Repository\FareRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FareRepository::class)]
#[ORM\Table(name: 'fare')]
#[ORM\Index(columns: ['target_type', 'target_id', 'is_active'])]
class Fare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: FareType::class, length: 30)]
    private FareType $fareType = FareType::Standard;

    #[ORM\Column(enumType: FareTargetType::class, length: 30)]
    private FareTargetType $targetType = FareTargetType::Route;

    #[ORM\Column]
    private ?int $targetId = null;

    #[ORM\ManyToOne(targetEntity: RouteStop::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RouteStop $departureStop = null;

    #[ORM\ManyToOne(targetEntity: RouteStop::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RouteStop $arrivalStop = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $pricePerKg = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Agency $agency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $amount = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFareType(): FareType
    {
        return $this->fareType;
    }

    public function setFareType(FareType $fareType): static
    {
        $this->fareType = $fareType;

        return $this;
    }

    public function getTargetType(): FareTargetType
    {
        return $this->targetType;
    }

    public function setTargetType(FareTargetType $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function setTargetId(int $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getDepartureStop(): ?RouteStop
    {
        return $this->departureStop;
    }

    public function setDepartureStop(?RouteStop $departureStop): static
    {
        $this->departureStop = $departureStop;

        return $this;
    }

    public function getArrivalStop(): ?RouteStop
    {
        return $this->arrivalStop;
    }

    public function setArrivalStop(?RouteStop $arrivalStop): static
    {
        $this->arrivalStop = $arrivalStop;

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

    public function getPricePerKg(): ?string
    {
        return $this->pricePerKg;
    }

    public function setPricePerKg(?string $pricePerKg): static
    {
        $this->pricePerKg = $pricePerKg;

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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;

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
}
