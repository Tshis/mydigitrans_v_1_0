<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommissionPaymentItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommissionPaymentItemRepository::class)]
#[ORM\Table(name: 'commission_payment_item')]
class CommissionPaymentItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Commission::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commission $commission = null;

    #[ORM\ManyToOne(targetEntity: CommissionPayment::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CommissionPayment $commissionPayment = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $amount = null;

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

    public function getCommission(): ?Commission
    {
        return $this->commission;
    }

    public function setCommission(Commission $commission): static
    {
        $this->commission = $commission;

        return $this;
    }

    public function getCommissionPayment(): ?CommissionPayment
    {
        return $this->commissionPayment;
    }

    public function setCommissionPayment(CommissionPayment $commissionPayment): static
    {
        $this->commissionPayment = $commissionPayment;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
