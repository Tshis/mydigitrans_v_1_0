<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'ticket')]
#[ORM\Index(columns: ['agency_id', 'status'])]
#[ORM\Index(columns: ['trip_seat_id'])]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agency $agency = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $ticketNumber = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: TripSeat::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?TripSeat $tripSeat = null;

    #[ORM\Column(length: 150)]
    private ?string $travelerName = null;

    #[ORM\Column(length: 30)]
    private ?string $travelerPhone = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $price = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column(enumType: TicketStatus::class, length: 30)]
    private TicketStatus $status = TicketStatus::Paid;

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

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    public function getTicketNumber(): ?string
    {
        return $this->ticketNumber;
    }

    public function setTicketNumber(string $ticketNumber): static
    {
        $this->ticketNumber = $ticketNumber;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getTripSeat(): ?TripSeat
    {
        return $this->tripSeat;
    }

    public function setTripSeat(TripSeat $tripSeat): static
    {
        $this->tripSeat = $tripSeat;

        return $this;
    }

    public function getTravelerName(): ?string
    {
        return $this->travelerName;
    }

    public function setTravelerName(string $travelerName): static
    {
        $this->travelerName = $travelerName;

        return $this;
    }

    public function getTravelerPhone(): ?string
    {
        return $this->travelerPhone;
    }

    public function setTravelerPhone(string $travelerPhone): static
    {
        $this->travelerPhone = $travelerPhone;

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

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
