<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaidByType;
use App\Enum\ProcessStatus;
use App\Repository\CommissionPaymentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommissionPaymentRepository::class)]
#[ORM\Table(name: 'commission_payment')]
#[ORM\Index(columns: ['user_id', 'status'])]
class CommissionPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $amount = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column(length: 50)]
    private ?string $method = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    #[ORM\Column(enumType: ProcessStatus::class, length: 30)]
    private ProcessStatus $status = ProcessStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(enumType: PaidByType::class, length: 30)]
    private PaidByType $paidBy = PaidByType::Platform;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $paidByUser = null;

    /** @var Collection<int, CommissionPaymentItem> */
    #[ORM\OneToMany(targetEntity: CommissionPaymentItem::class, mappedBy: 'commissionPayment')]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

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

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStatus(): ProcessStatus
    {
        return $this->status;
    }

    public function setStatus(ProcessStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

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

    public function getPaidBy(): PaidByType
    {
        return $this->paidBy;
    }

    public function setPaidBy(PaidByType $paidBy): static
    {
        $this->paidBy = $paidBy;

        return $this;
    }

    public function getPaidByUser(): ?User
    {
        return $this->paidByUser;
    }

    public function setPaidByUser(?User $paidByUser): static
    {
        $this->paidByUser = $paidByUser;

        return $this;
    }

    /** @return Collection<int, CommissionPaymentItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CommissionPaymentItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCommissionPayment($this);
        }

        return $this;
    }

    public function removeItem(CommissionPaymentItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }
}
