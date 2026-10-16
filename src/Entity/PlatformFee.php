<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FeeSourceType;
use App\Enum\PlatformFeeStatus;
use App\Repository\PlatformFeeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformFeeRepository::class)]
#[ORM\Table(name: 'platform_fee')]
#[ORM\UniqueConstraint(columns: ['rule_id', 'source_type', 'source_id'])]
#[ORM\Index(columns: ['agency_id', 'status'])]
#[ORM\Index(columns: ['invoice_id'])]
class PlatformFee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agency $agency = null;

    #[ORM\ManyToOne(targetEntity: PlatformFeeRule::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlatformFeeRule $rule = null;

    #[ORM\Column(enumType: FeeSourceType::class, length: 30)]
    private FeeSourceType $sourceType = FeeSourceType::Reservation;

    #[ORM\Column]
    private ?int $sourceId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $amount = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column(enumType: PlatformFeeStatus::class, length: 30)]
    private PlatformFeeStatus $status = PlatformFeeStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Invoice $invoice = null;

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

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    public function getRule(): ?PlatformFeeRule
    {
        return $this->rule;
    }

    public function setRule(PlatformFeeRule $rule): static
    {
        $this->rule = $rule;

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

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(int $sourceId): static
    {
        $this->sourceId = $sourceId;

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

    public function getStatus(): PlatformFeeStatus
    {
        return $this->status;
    }

    public function setStatus(PlatformFeeStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
