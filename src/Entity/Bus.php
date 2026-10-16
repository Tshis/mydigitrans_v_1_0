<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BusStatus;
use App\Repository\BusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BusRepository::class)]
#[ORM\Table(name: 'bus')]
#[ORM\UniqueConstraint(columns: ['agency_id', 'code'])]
#[ORM\Index(columns: ['agency_id', 'status'])]
class Bus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agency $agency = null;

    #[ORM\ManyToOne(targetEntity: BusLayout::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?BusLayout $busLayout = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $plateNumber = null;

    #[ORM\Column(length: 100)]
    private ?string $brand = null;

    #[ORM\Column(length: 100)]
    private ?string $model = null;

    #[ORM\Column(enumType: BusStatus::class, length: 30)]
    private BusStatus $status = BusStatus::Active;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $inactiveReason = null;

    #[ORM\Column]
    private int $mileage = 0;

    #[ORM\Column]
    private ?int $year = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /** @var Collection<int, Seat> */
    #[ORM\OneToMany(targetEntity: Seat::class, mappedBy: 'bus')]
    private Collection $seats;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->seats = new ArrayCollection();
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

    public function getBusLayout(): ?BusLayout
    {
        return $this->busLayout;
    }

    public function setBusLayout(BusLayout $busLayout): static
    {
        $this->busLayout = $busLayout;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPlateNumber(): ?string
    {
        return $this->plateNumber;
    }

    public function setPlateNumber(string $plateNumber): static
    {
        $this->plateNumber = $plateNumber;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getStatus(): BusStatus
    {
        return $this->status;
    }

    public function setStatus(BusStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInactiveReason(): ?string
    {
        return $this->inactiveReason;
    }

    public function setInactiveReason(?string $inactiveReason): static
    {
        $this->inactiveReason = $inactiveReason;

        return $this;
    }

    public function getMileage(): int
    {
        return $this->mileage;
    }

    public function setMileage(int $mileage): static
    {
        $this->mileage = $mileage;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

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

    /** @return Collection<int, Seat> */
    public function getSeats(): Collection
    {
        return $this->seats;
    }

    public function addSeat(Seat $seat): static
    {
        if (!$this->seats->contains($seat)) {
            $this->seats->add($seat);
            $seat->setBus($this);
        }

        return $this;
    }

    public function removeSeat(Seat $seat): static
    {
        $this->seats->removeElement($seat);

        return $this;
    }
}
