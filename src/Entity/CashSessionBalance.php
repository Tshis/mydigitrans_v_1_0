<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CashSessionBalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashSessionBalanceRepository::class)]
#[ORM\Table(name: 'cash_session_balance')]
#[ORM\UniqueConstraint(columns: ['cash_session_id', 'currency_id'])]
class CashSessionBalance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashSession::class, inversedBy: 'balances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CashSession $cashSession = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

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

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

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
