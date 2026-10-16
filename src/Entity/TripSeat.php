<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TripSeatStatus;
use App\Repository\TripSeatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripSeatRepository::class)]
#[ORM\Table(name: 'trip_seat')]
#[ORM\UniqueConstraint(columns: ['trip_id', 'seat_id'])]
#[ORM\Index(columns: ['trip_id', 'seat_status'])]
class TripSeat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trip::class, inversedBy: 'tripSeats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(targetEntity: Seat::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Seat $seat = null;

    #[ORM\Column(enumType: TripSeatStatus::class, length: 30)]
    private TripSeatStatus $seatStatus = TripSeatStatus::Available;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Reservation $reservation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $heldUntil = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSeat(): ?Seat
    {
        return $this->seat;
    }

    public function setSeat(Seat $seat): static
    {
        $this->seat = $seat;

        return $this;
    }

    public function getSeatStatus(): TripSeatStatus
    {
        return $this->seatStatus;
    }

    public function setSeatStatus(TripSeatStatus $seatStatus): static
    {
        $this->seatStatus = $seatStatus;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getHeldUntil(): ?\DateTimeImmutable
    {
        return $this->heldUntil;
    }

    public function setHeldUntil(?\DateTimeImmutable $heldUntil): static
    {
        $this->heldUntil = $heldUntil;

        return $this;
    }
}
