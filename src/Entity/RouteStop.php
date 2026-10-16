<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RouteStopRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteStopRepository::class)]
#[ORM\Table(name: 'route_stop')]
#[ORM\UniqueConstraint(columns: ['route_id', 'stop_order'])]
class RouteStop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class, inversedBy: 'routeStops')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Route $route = null;

    #[ORM\Column]
    private ?int $stopOrder = null;

    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 100)]
    private ?string $country = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $distanceFromDeparture = null;

    #[ORM\Column]
    private ?int $durationFromDeparture = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(Route $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getStopOrder(): ?int
    {
        return $this->stopOrder;
    }

    public function setStopOrder(int $stopOrder): static
    {
        $this->stopOrder = $stopOrder;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getDistanceFromDeparture(): ?string
    {
        return $this->distanceFromDeparture;
    }

    public function setDistanceFromDeparture(string $distanceFromDeparture): static
    {
        $this->distanceFromDeparture = $distanceFromDeparture;

        return $this;
    }

    public function getDurationFromDeparture(): ?int
    {
        return $this->durationFromDeparture;
    }

    public function setDurationFromDeparture(int $durationFromDeparture): static
    {
        $this->durationFromDeparture = $durationFromDeparture;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
}
