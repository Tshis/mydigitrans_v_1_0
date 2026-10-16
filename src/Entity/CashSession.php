<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CashSessionStatus;
use App\Repository\CashSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashSessionRepository::class)]
#[ORM\Table(name: 'cash_session')]
#[ORM\Index(columns: ['cash_register_id', 'status'])]
class CashSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashRegister::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?CashRegister $cashRegister = null;

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

    /** @var Collection<int, CashSessionBalance> */
    #[ORM\OneToMany(targetEntity: CashSessionBalance::class, mappedBy: 'cashSession')]
    private Collection $balances;

    public function __construct()
    {
        $this->openedAt = new \DateTimeImmutable();
        $this->balances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashRegister(): ?CashRegister
    {
        return $this->cashRegister;
    }

    public function setCashRegister(CashRegister $cashRegister): static
    {
        $this->cashRegister = $cashRegister;

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

    /** @return Collection<int, CashSessionBalance> */
    public function getBalances(): Collection
    {
        return $this->balances;
    }

    public function addBalance(CashSessionBalance $balance): static
    {
        if (!$this->balances->contains($balance)) {
            $this->balances->add($balance);
            $balance->setCashSession($this);
        }

        return $this;
    }

    public function removeBalance(CashSessionBalance $balance): static
    {
        $this->balances->removeElement($balance);

        return $this;
    }
}
