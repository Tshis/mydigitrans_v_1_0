<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FinancialReferenceType;
use App\Enum\FlowType;
use App\Repository\FinancialOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialOperationRepository::class)]
#[ORM\Table(name: 'financial_operation')]
#[ORM\Index(columns: ['cash_session_id'])]
#[ORM\Index(columns: ['reference_type', 'reference_id'])]
class FinancialOperation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashSession::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?CashSession $cashSession = null;

    #[ORM\Column(enumType: FlowType::class, length: 30)]
    private FlowType $type = FlowType::In;

    #[ORM\Column(enumType: FinancialReferenceType::class, length: 30)]
    private FinancialReferenceType $referenceType = FinancialReferenceType::AgencyPayment;

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

    public function getCashSession(): ?CashSession
    {
        return $this->cashSession;
    }

    public function setCashSession(CashSession $cashSession): static
    {
        $this->cashSession = $cashSession;

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

    public function getReferenceType(): FinancialReferenceType
    {
        return $this->referenceType;
    }

    public function setReferenceType(FinancialReferenceType $referenceType): static
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
