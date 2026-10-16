<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RouteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteRepository::class)]
#[ORM\Table(name: 'route')]
#[ORM\UniqueConstraint(columns: ['agency_id', 'code'])]
#[ORM\UniqueConstraint(columns: ['agency_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class Route
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agency $agency = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 160)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $distance = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUpdatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $reservationExpirationDelay = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $pricePerKg = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Currency $currencyPricePerKg = null;

    /** @var Collection<int, RouteStop> */
    #[ORM\OneToMany(targetEntity: RouteStop::class, mappedBy: 'route')]
    #[ORM\OrderBy(['stopOrder' => 'ASC'])]
    private Collection $routeStops;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->routeStops = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->lastUpdatedAt = new \DateTimeImmutable();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDistance(): ?string
    {
        return $this->distance;
    }

    public function setDistance(string $distance): static
    {
        $this->distance = $distance;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

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

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getLastUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(?\DateTimeImmutable $lastUpdatedAt): static
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }

    public function getReservationExpirationDelay(): ?int
    {
        return $this->reservationExpirationDelay;
    }

    public function setReservationExpirationDelay(?int $reservationExpirationDelay): static
    {
        $this->reservationExpirationDelay = $reservationExpirationDelay;

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

    public function getCurrencyPricePerKg(): ?Currency
    {
        return $this->currencyPricePerKg;
    }

    public function setCurrencyPricePerKg(?Currency $currencyPricePerKg): static
    {
        $this->currencyPricePerKg = $currencyPricePerKg;

        return $this;
    }

    /** @return Collection<int, RouteStop> */
    public function getRouteStops(): Collection
    {
        return $this->routeStops;
    }

    public function addRouteStop(RouteStop $routeStop): static
    {
        if (!$this->routeStops->contains($routeStop)) {
            $this->routeStops->add($routeStop);
            $routeStop->setRoute($this);
        }

        return $this;
    }

    public function removeRouteStop(RouteStop $routeStop): static
    {
        $this->routeStops->removeElement($routeStop);

        return $this;
    }
}
