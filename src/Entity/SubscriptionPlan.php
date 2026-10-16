<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ActiveStatus;
use App\Repository\SubscriptionPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\Table(name: 'subscription_plan')]
class SubscriptionPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $price = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column]
    private ?int $durationDays = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxBranches = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxUsers = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxBuses = null;

    #[ORM\Column(enumType: ActiveStatus::class, length: 30)]
    private ActiveStatus $status = ActiveStatus::Active;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /** @var Collection<int, PlanFeature> */
    #[ORM\OneToMany(targetEntity: PlanFeature::class, mappedBy: 'plan')]
    private Collection $planFeatures;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->planFeatures = new ArrayCollection();
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

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

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): static
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    public function getMaxBranches(): ?int
    {
        return $this->maxBranches;
    }

    public function setMaxBranches(?int $maxBranches): static
    {
        $this->maxBranches = $maxBranches;

        return $this;
    }

    public function getMaxUsers(): ?int
    {
        return $this->maxUsers;
    }

    public function setMaxUsers(?int $maxUsers): static
    {
        $this->maxUsers = $maxUsers;

        return $this;
    }

    public function getMaxBuses(): ?int
    {
        return $this->maxBuses;
    }

    public function setMaxBuses(?int $maxBuses): static
    {
        $this->maxBuses = $maxBuses;

        return $this;
    }

    public function getStatus(): ActiveStatus
    {
        return $this->status;
    }

    public function setStatus(ActiveStatus $status): static
    {
        $this->status = $status;

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

    /** @return Collection<int, PlanFeature> */
    public function getPlanFeatures(): Collection
    {
        return $this->planFeatures;
    }

    public function addPlanFeature(PlanFeature $planFeature): static
    {
        if (!$this->planFeatures->contains($planFeature)) {
            $this->planFeatures->add($planFeature);
            $planFeature->setPlan($this);
        }

        return $this;
    }

    public function removePlanFeature(PlanFeature $planFeature): static
    {
        $this->planFeatures->removeElement($planFeature);

        return $this;
    }
}
