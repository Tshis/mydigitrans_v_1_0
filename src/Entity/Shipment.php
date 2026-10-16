<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BillingType;
use App\Enum\ShipmentStatus;
use App\Repository\ShipmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
#[ORM\Table(name: 'shipment')]
#[ORM\Index(columns: ['agency_id', 'status'])]
#[ORM\Index(columns: ['trip_id'])]
class Shipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: Trip::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $receiver = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $pickupCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $receiverIdCardNumber = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $nature = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Branch $departureAgencyBranch = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Branch $arrivalAgencyBranch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $weight = null;

    #[ORM\Column(enumType: BillingType::class, length: 30)]
    private BillingType $billingType = BillingType::Weight;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $price = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agency $agency = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Branch $branch = null;

    #[ORM\Column(enumType: ShipmentStatus::class, length: 30)]
    private ShipmentStatus $status = ShipmentStatus::Pending;

    #[ORM\ManyToOne(targetEntity: RouteStop::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?RouteStop $departureStop = null;

    #[ORM\ManyToOne(targetEntity: RouteStop::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?RouteStop $arrivalStop = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(Trip $trip): static
    {
        $this->trip = $trip;

        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(User $receiver): static
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getPickupCode(): ?string
    {
        return $this->pickupCode;
    }

    public function setPickupCode(string $pickupCode): static
    {
        $this->pickupCode = $pickupCode;

        return $this;
    }

    public function getReceiverIdCardNumber(): ?string
    {
        return $this->receiverIdCardNumber;
    }

    public function setReceiverIdCardNumber(?string $receiverIdCardNumber): static
    {
        $this->receiverIdCardNumber = $receiverIdCardNumber;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(string $nature): static
    {
        $this->nature = $nature;

        return $this;
    }

    public function getDepartureAgencyBranch(): ?Branch
    {
        return $this->departureAgencyBranch;
    }

    public function setDepartureAgencyBranch(Branch $departureAgencyBranch): static
    {
        $this->departureAgencyBranch = $departureAgencyBranch;

        return $this;
    }

    public function getArrivalAgencyBranch(): ?Branch
    {
        return $this->arrivalAgencyBranch;
    }

    public function setArrivalAgencyBranch(Branch $arrivalAgencyBranch): static
    {
        $this->arrivalAgencyBranch = $arrivalAgencyBranch;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(string $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getBillingType(): BillingType
    {
        return $this->billingType;
    }

    public function setBillingType(BillingType $billingType): static
    {
        $this->billingType = $billingType;

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

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getStatus(): ShipmentStatus
    {
        return $this->status;
    }

    public function setStatus(ShipmentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDepartureStop(): ?RouteStop
    {
        return $this->departureStop;
    }

    public function setDepartureStop(RouteStop $departureStop): static
    {
        $this->departureStop = $departureStop;

        return $this;
    }

    public function getArrivalStop(): ?RouteStop
    {
        return $this->arrivalStop;
    }

    public function setArrivalStop(RouteStop $arrivalStop): static
    {
        $this->arrivalStop = $arrivalStop;

        return $this;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
