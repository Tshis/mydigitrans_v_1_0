<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CashSessionStatus;
use App\Repository\PlatformCashSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformCashSessionRepository::class)]
#[ORM\Table(name: 'platform_cash_session')]
#[ORM\Index(columns: ['platform_cash_register_id', 'status'])]
class PlatformCashSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlatformCashRegister::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlatformCashRegister $platformCashRegister = null;

    #[ORM\ManyToOne(targetEntity: PlatformBranch::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlatformBranch $platformBranch = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $openedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(enumType: CashSessionStatus::class, length: 30)]
    private CashSessionStatus $status = CashSessionStatus::Opened;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $openingBalanceExpected = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $openingBalanceDeclared = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $closingBalanceExpected = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $closingBalanceDeclared = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $openBalanceDifference = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $closingBalanceDifference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $openingObservation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $closingObservation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $managerConclusion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $openingNote = null;

    public function __construct()
    {
        $this->openedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlatformCashRegister(): ?PlatformCashRegister
    {
        return $this->platformCashRegister;
    }

    public function setPlatformCashRegister(PlatformCashRegister $platformCashRegister): static
    {
        $this->platformCashRegister = $platformCashRegister;

        return $this;
    }

    public function getPlatformBranch(): ?PlatformBranch
    {
        return $this->platformBranch;
    }

    public function setPlatformBranch(PlatformBranch $platformBranch): static
    {
        $this->platformBranch = $platformBranch;

        return $this;
    }

    public function getOpenedBy(): ?User
    {
        return $this->openedBy;
    }

    public function setOpenedBy(User $openedBy): static
    {
        $this->openedBy = $openedBy;

        return $this;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getStatus(): CashSessionStatus
    {
        return $this->status;
    }

    public function setStatus(CashSessionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOpeningBalanceExpected(): string
    {
        return $this->openingBalanceExpected;
    }

    public function setOpeningBalanceExpected(string $openingBalanceExpected): static
    {
        $this->openingBalanceExpected = $openingBalanceExpected;

        return $this;
    }

    public function getOpeningBalanceDeclared(): string
    {
        return $this->openingBalanceDeclared;
    }

    public function setOpeningBalanceDeclared(string $openingBalanceDeclared): static
    {
        $this->openingBalanceDeclared = $openingBalanceDeclared;

        return $this;
    }

    public function getClosingBalanceExpected(): ?string
    {
        return $this->closingBalanceExpected;
    }

    public function setClosingBalanceExpected(?string $closingBalanceExpected): static
    {
        $this->closingBalanceExpected = $closingBalanceExpected;

        return $this;
    }

    public function getClosingBalanceDeclared(): ?string
    {
        return $this->closingBalanceDeclared;
    }

    public function setClosingBalanceDeclared(?string $closingBalanceDeclared): static
    {
        $this->closingBalanceDeclared = $closingBalanceDeclared;

        return $this;
    }

    public function getOpenBalanceDifference(): string
    {
        return $this->openBalanceDifference;
    }

    public function setOpenBalanceDifference(string $openBalanceDifference): static
    {
        $this->openBalanceDifference = $openBalanceDifference;

        return $this;
    }

    public function getClosingBalanceDifference(): ?string
    {
        return $this->closingBalanceDifference;
    }

    public function setClosingBalanceDifference(?string $closingBalanceDifference): static
    {
        $this->closingBalanceDifference = $closingBalanceDifference;

        return $this;
    }

    public function getOpeningObservation(): ?string
    {
        return $this->openingObservation;
    }

    public function setOpeningObservation(?string $openingObservation): static
    {
        $this->openingObservation = $openingObservation;

        return $this;
    }

    public function getClosingObservation(): ?string
    {
        return $this->closingObservation;
    }

    public function setClosingObservation(?string $closingObservation): static
    {
        $this->closingObservation = $closingObservation;

        return $this;
    }

    public function getManagerConclusion(): ?string
    {
        return $this->managerConclusion;
    }

    public function setManagerConclusion(?string $managerConclusion): static
    {
        $this->managerConclusion = $managerConclusion;

        return $this;
    }

    public function getOpeningNote(): ?string
    {
        return $this->openingNote;
    }

    public function setOpeningNote(?string $openingNote): static
    {
        $this->openingNote = $openingNote;

        return $this;
    }
}
