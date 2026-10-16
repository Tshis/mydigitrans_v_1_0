<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FlowType;
use App\Enum\PlatformOperationType;
use App\Enum\PlatformReferenceType;
use App\Repository\PlatformFinancialOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformFinancialOperationRepository::class)]
#[ORM\Table(name: 'platform_financial_operation')]
#[ORM\Index(columns: ['platform_cash_session_id'])]
#[ORM\Index(columns: ['reference_type', 'reference_id'])]
class PlatformFinancialOperation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlatformCashSession::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlatformCashSession $platformCashSession = null;

    #[ORM\Column(enumType: PlatformOperationType::class, length: 30)]
    private PlatformOperationType $operationType = PlatformOperationType::Commission;

    #[ORM\Column(enumType: FlowType::class, length: 30)]
    private FlowType $type = FlowType::In;

    #[ORM\Column(enumType: PlatformReferenceType::class, length: 30)]
    private PlatformReferenceType $referenceType = PlatformReferenceType::Invoice;

    #[ORM\Column]
    private ?int $referenceId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $amount = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlatformCashSession(): ?PlatformCashSession
    {
        return $this->platformCashSession;
    }

    public function setPlatformCashSession(PlatformCashSession $platformCashSession): static
    {
        $this->platformCashSession = $platformCashSession;

        return $this;
    }

    public function getOperationType(): PlatformOperationType
    {
        return $this->operationType;
    }

    public function setOperationType(PlatformOperationType $operationType): static
    {
        $this->operationType = $operationType;

        return $this;
    }

    public function getType(): FlowType
    {
        return $this->type;
    }

    public function setType(FlowType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getReferenceType(): PlatformReferenceType
    {
        return $this->referenceType;
    }

    public function setReferenceType(PlatformReferenceType $referenceType): static
    {
        $this->referenceType = $referenceType;

        return $this;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function setReferenceId(int $referenceId): static
    {
        $this->referenceId = $referenceId;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
