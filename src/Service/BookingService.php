<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\line\BookingSummary;
use App\Dto\public\line\BookingData;
use App\Entity\CurrencyAgency;
use App\Entity\Fare;
use App\Entity\Reservation;
use App\Entity\RouteStop;
use App\Entity\Trip;
use App\Entity\TripSeat;
use App\Entity\User;
use App\Enum\FareTargetType;
use App\Enum\ReservationStatus;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use App\Enum\TripStatus;
use App\Enum\UserType;
use App\Exception\BookingException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BookingService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Vérifie si le voyage est éligible à la réservation publique.
     */
    public function assertBookable(Trip $trip, \DateTimeImmutable $now): void
    {
        if ($trip->getStatus() !== TripStatus::Scheduled) {
            throw new BookingException('Le statut de ce voyage ne permet plus de réservations.');
        }

        $departureAt = $trip->getDepartureDate()->setTime(
            (int) $trip->getDepartureTime()->format('H'),
            (int) $trip->getDepartureTime()->format('i')
        );

        if ($departureAt <= $now) {
            throw new BookingException('Ce voyage est déjà parti ou est en cours de départ.');
        }
    }

    /**
     * Valide et extrait les arrêts de montée et descente sur la route du voyage.
     *
     * @return array{0: RouteStop, 1: RouteStop}
     */
    public function resolveSegment(Trip $trip, ?string $departureStopId, ?string $arrivalStopId): array
    {
        if (null === $departureStopId || null === $arrivalStopId) {
            throw new BookingException('Les gares de départ et d\'arrivée sont obligatoires.');
        }

        $routeStopRepo = $this->em->getRepository(RouteStop::class);
        $departureStop = $routeStopRepo->find((int) $departureStopId);
        $arrivalStop = $routeStopRepo->find((int) $arrivalStopId);

        if (!$departureStop || !$arrivalStop || !$departureStop->IsActive() || !$arrivalStop->IsActive()) {
            throw new BookingException('L\'un des arrêts sélectionnés n\'est plus actif.');
        }

        if ($departureStop->getRoute() !== $trip->getRoute() || $arrivalStop->getRoute() !== $trip->getRoute()) {
            throw new BookingException('Ces arrêts n\'appartiennent pas à l\'itinéraire de ce voyage.');
        }

        if ($departureStop->getStopOrder() >= $arrivalStop->getStopOrder()) {
            throw new BookingException('L\'ordre des gares sélectionnées est invalide.');
        }

        return [$departureStop, $arrivalStop];
    }

    /**
     * Calcule le prix réel du trajet depuis les Fares de l'ORM (Sécurisé).
     *
     * @return array{amount: string, currency: \App\Entity\Currency}
     */
    public function quote(Trip $trip, RouteStop $departure, RouteStop $arrival, SeatType $seatType, \DateTimeImmutable $now): array
    {
        $fares = $this->em->getRepository(Fare::class)->findBy([
            'targetType' => FareTargetType::Route,
            'targetId' => (int) $trip->getRoute()?->getId(),
            'isActive' => true
        ]);

        $agency = $trip->getRoute()?->getAgency();
        $currencyAgency = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency' => $agency,
            'isBase' => true,
            'isActive' => true
        ]);

        $baseCurrency = $currencyAgency?->getCurrency();
        if (!$baseCurrency) {
            throw new BookingException('La devise de base de l\'agence n\'est pas configurée.');
        }

        $finalAmount = 0.0;

        foreach ($fares as $fare) {
            if ($fare->getDepartureStop() !== null && $fare->getArrivalStop() !== null) {
                if (
                    $fare->getDepartureStop()->getId() === $departure->getId() &&
                    $fare->getArrivalStop()->getId() === $arrival->getId()
                ) {
                    $finalAmount = (float) $fare->getAmount();
                    break;
                }
            }
            if ($fare->getDepartureStop() === null && $fare->getArrivalStop() === null && $finalAmount === 0.0) {
                $finalAmount = (float) $fare->getAmount();
            }
        }

        return [
            'amount' => number_format($finalAmount, 2, '.', ''),
            'currency' => $baseCurrency
        ];
    }

    /**
     * Calcule l'heure exacte de passage du bus à l'arrêt d'embarquement.
     */
    public function departureAt(Trip $trip, RouteStop $departureStop): \DateTimeImmutable
    {
        $base = \DateTimeImmutable::createFromInterface($trip->getDepartureDate())
            ->setTime((int) $trip->getDepartureTime()->format('H'), (int) $trip->getDepartureTime()->format('i'), 0);

        return $base->add(new \DateInterval(sprintf('PT%dM', $departureStop->getDurationFromDeparture())));
    }

    /**
     * Échéance limite d'annulation (Règle de carence : 2 heures avant le départ à l'arrêt).
     */
    public function stationDeadline(Reservation $reservation, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $departureAt = $this->departureAt($reservation->getTrip(), $reservation->getDepartureStop());
        $deadline = $departureAt->modify('-2 hours');

        return $deadline > $now ? $deadline : null;
    }


    /**
     * ÉTAPE 1 : Enregistrement de la Réservation avec Multi-sièges [MCD 17, 22]
     */
    public function createPendingReservation(Trip $trip, RouteStop $departure, RouteStop $arrival, BookingData $data, ?User $currentUser, \DateTimeImmutable $now): Reservation
    {
        // 1. Récupération de la liste complète des sièges soumis (ex: ["5", "6"])
        $seatsList = array_filter(array_map('trim', $data->seats));

        if (empty($seatsList)) {
            throw new BookingException('Veuillez choisir au moins un siège sur le plan du bus.');
        }

        // 2. ◄ VÉRIFICATION & EXTRACTION DE TOUS LES TRIPSEATS CONCERNÉS
        $tripSeats = [];
        foreach ($seatsList as $seatLabel) {
            $tripSeat = $this->em->getRepository(TripSeat::class)->createQueryBuilder('ts')
                ->join('ts.seat', 's')
                ->join('ts.trip', 't')
                ->andWhere('t.id = :tripId')
                ->andWhere('s.seatLabel = :seatLabel')
                ->setParameter('tripId', (int) $trip->getId())
                ->setParameter('seatLabel', (string) $seatLabel)
                ->getQuery()
                ->getOneOrNullResult();

            // SÉCURITÉ CONCURRENCE : Contrôle de disponibilité pour chaque siège de la liste
            if (!$tripSeat || $tripSeat->getSeatStatus() !== TripSeatStatus::Available) {

                throw new BookingException(sprintf('Désolé, le siège %s n\'est plus disponible.', $seatLabel));
            }



            $tripSeats[] = $tripSeat;
        }

        // 3. Gestion anti-doublon de l'identité du voyageur
        $parts = array_filter(explode(' ', trim((string) $data->fullName)));
        $firstname = array_shift($parts) ?? 'Passager';
        $lastname = !empty($parts) ? implode(' ', $parts) : 'Client';

        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $data->phone);
        $generatedEmail = sprintf('rsv.%s@mydigitrans.local', $cleanPhone ?: bin2hex(random_bytes(4)));

        $user = $currentUser;
        if (!$user) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $generatedEmail]);
        }

        if (!$user) {
            $user = new User();
            $user->setEmail($generatedEmail);
            $user->setFirstname($firstname);
            $user->setLastname($lastname);
            $user->setPhone($data->phone);
            $user->setUserType(UserType::Client);
            $user->setIsActive(true);
            $user->setPassword(bin2hex(random_bytes(16)));

            if (method_exists($user, 'setCode')) {
                $user->setCode(sprintf('CLT-%s', $cleanPhone ?: strtoupper(bin2hex(random_bytes(4)))));
            }
            $this->em->persist($user);
        } else {
            $user->setFirstname($firstname);
            $user->setLastname($lastname);
            $user->setPhone($data->phone);
        }

        // 4. Calcul du montant cumulé (Prix par siège x Nombre de sièges)
        $quote = $this->quote($trip, $departure, $arrival, SeatType::Normal, $now);
        $unitPrice = (float) $quote['amount'];
        $totalAmount = $unitPrice * count($tripSeats); // Cumul financier global

        // 5. Génération de la Réservation unique avec ton timestamp séquentiel
        $reference = $this->generateReference();

        $reservation = (new Reservation())
            ->setReference($reference)
            ->setTrip($trip)
            ->setDepartureStop($departure)
            ->setArrivalStop($arrival)
            ->setAgency($trip->getRoute()?->getAgency())
            ->setUser($user)
            ->setStatus(ReservationStatus::Pending)
            ->setTotalAmount((string) $totalAmount) // Enregistre la somme totale (ex: 30 000 CDF pour 2 places)
            ->setCurrency($quote['currency'])
            ->setCreatedBy($currentUser ?? $user);

        $this->em->persist($reservation);

        // 6. ◄ VERROUILLAGE DE TOUS LES SIÈGES RETENUS DANS LA BD
        foreach ($tripSeats as $ts) {
            $ts->setSeatStatus(TripSeatStatus::Held);
            $ts->setHeldUntil($now->modify('+15 minutes'));
            $ts->setReservation($reservation);
        }

        // 7. Flush unique de la transaction complète dans PostgreSQL
        $this->em->flush();

        return $reservation;
    }//createPendingReservation

    /**
     * ÉTAPE 3 : Validation définitive au comptoir de la gare.
     */
    public function confirmStationPayment(Reservation $reservation, \DateTimeImmutable $now): void
    {
        $tripSeat = $this->em->getRepository(TripSeat::class)->findOneBy(['reservation' => $reservation]);

        $reservation->setStatus(ReservationStatus::Pending);
        $deadline = $this->stationDeadline($reservation, $now);
        $reservation->setExpiresAt($deadline);

        if ($tripSeat) {
            $tripSeat->setSeatStatus(TripSeatStatus::Held);
            $tripSeat->setHeldUntil($deadline);
        }

        $this->em->flush();
    } //confirmStationPayment

    public function findPendingReservation(string $reference, \DateTimeImmutable $now): Reservation
    {
        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['reference' => $reference]);

        if (!$reservation) {
            throw new BookingException('Cette référence de réservation est introuvable.');
        }

        return $reservation;
    } //findPendingReservation

    public function findReservation(string $reference): ?Reservation
    {
        return $this->em->getRepository(Reservation::class)->findOneBy(['reference' => $reference]);
    } //findReservation

    public function seatInfo(Trip $trip, RouteStop $departure, RouteStop $arrival, \DateTimeImmutable $now): array
    {
        $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['trip' => $trip]);
        $info = [];
        foreach ($tripSeats as $ts) {

            $statusEnum = $ts->getSeatStatus();

            // ◄ EXTRACTION DE LA VALEUR BRUTE : Convertit l'Enum en string ("available", "held", etc.)
            $status = $statusEnum instanceof \BackedEnum ? $statusEnum->value : (string) $statusEnum;
            if ($status === TripSeatStatus::Held && $ts->getHeldUntil() && $ts->getHeldUntil() < $now) {
                $status = TripSeatStatus::Available;
            }
            $info[$ts->getSeat()?->getSeatLabel()] = $status;
        }
        return $info;
    } //seatInfo

    public function summary1(Reservation $reservation): BookingSummary
    {
        $user = $reservation->getUser();
        $trip = $reservation->getTrip();
        $bus = $trip?->getBus();
        $tripSeat = $this->em->getRepository(TripSeat::class)->findOneBy(['reservation' => $reservation]);
        $seatLabel = $tripSeat ? $tripSeat->getSeat()?->getSeatLabel() : 'Non assigné';
        $departureAt = $this->departureAt($trip, $reservation->getDepartureStop());
        return new BookingSummary(
            id: (int) $reservation->getId(),
            reference: $reservation->getReference(),
            status: $reservation->getStatus()->value,
            tripCode: $trip?->getCode() ?? 'TRIP-UNK',
            routeLabel: $trip?->getRoute()?->getName() ?? 'Ligne Inconnue',
            agencyName: $reservation->getAgency()?->getName() ?? 'Agence Indéfinie',
            departureCity: $reservation->getDepartureStop()?->getCity() ?? '',
            arrivalCity: $reservation->getArrivalStop()?->getCity() ?? '',
            busModel: trim(($bus?->getBrand() ?? '') . ' ' . ($bus?->getModel() ?? 'Standard')),
            departureAt: $departureAt,
            expiresAt: $reservation->getExpiresAt(),
            passengerName: $user ? (string) $user->getFirstname() . ' ' . $user->getLastname() : 'Voyageur Anonyme',
            passengerPhone: $user ? (string) $user->getPhone() : '-',
            seats: [$seatLabel],
            fareCategory: $tripSeat && 'vip' === strtolower((string)$tripSeat->getSeat()?->getType()->value) ? 'VIP' : 'Standard',
            total: number_format((float) $reservation->getTotalAmount(), 2, ',', ' '),
            currencyCode: $reservation->getCurrency()?->getCode() ?? 'CDF'
        );
    } //summary1

    public function summary(Reservation $reservation): BookingSummary
    {
        $user = $reservation->getUser();
        $trip = $reservation->getTrip();
        $bus = $trip?->getBus();

        // ◄ EXTRACTION DE LA LISTE COMPLÈTE DES SIÈGES LIÉS
        $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['reservation' => $reservation]);
        $seatsLabels = array_map(static fn($ts) => $ts->getSeat()?->getSeatLabel(), $tripSeats);

        $departureAt = $this->departureAt($trip, $reservation->getDepartureStop());

        return new BookingSummary(
            id: (int) $reservation->getId(),
            reference: $reservation->getReference(),
            status: $reservation->getStatus()->value,
            tripCode: $trip?->getCode() ?? 'TRIP-UNK',
            routeLabel: $trip?->getRoute()?->getName() ?? 'Ligne Inconnue',
            agencyName: $reservation->getAgency()?->getName() ?? 'Agence Indéfinie',
            departureCity: $reservation->getDepartureStop()?->getCity() ?? '',
            arrivalCity: $reservation->getArrivalStop()?->getCity() ?? '',
            busModel: trim(($bus?->getBrand() ?? '') . ' ' . ($bus?->getModel() ?? 'Standard')),
            departureAt: $departureAt,
            expiresAt: $reservation->getExpiresAt(),
            passengerName: $user ? (string) $user->getFirstname() . ' ' . $user->getLastname() : 'Voyageur Anonyme',
            passengerPhone: $user ? (string) $user->getPhone() : '-',
            seats: $seatsLabels, // ◄ Envoie le tableau complet (ex: ["5", "6"]) à Twig
            fareCategory: 'Standard',
            total: number_format((float) $reservation->getTotalAmount(), 2, ',', ' '),
            currencyCode: $reservation->getCurrency()?->getCode() ?? 'CDF'
        );
    }//summary


    /**
     * Génère une référence de réservation professionnelle basée sur un horodatage (ex: RSV-MDT-1792589042)
     */
    public function generateReference(): string
    {
        // Récupère le timestamp Unix actuel (nombre de secondes écoulées depuis 1970)
        $timestamp = time();

        return sprintf('RSV-MDT-%d', $timestamp);
    } //generateReference
}
