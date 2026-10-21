<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\line\BookingData;
use App\Dto\public\line\BookingSummary;
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

final readonly class BookingService1
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Vérifie si le voyage est éligible à la réservation publique.
     */
    public function assertBookable(Trip $trip, \DateTimeImmutable $now): void
    {


        if ($trip->getStatus() !== TripStatus::Scheduled) {
            throw new BookingException('Ce voyage n\'est plus ouvert aux réservations.');
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

        $route = $trip->getRoute();
        if ($departureStop->getRoute() !== $route || $arrivalStop->getRoute() !== $route) {
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
            'targetType' => FareTargetType::Route->value,
            'targetId' => (string) $trip->getRoute()?->getId(),
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
            // Tronçon spécifique (ex: Lubumbashi -> Likasi)
            if ($fare->getDepartureStop() !== null && $fare->getArrivalStop() !== null) {
                if (
                    $fare->getDepartureStop()->getId() === $departure->getId() &&
                    $fare->getArrivalStop()->getId() === $arrival->getId()
                ) {
                    $finalAmount = (float) $fare->getAmount();
                    break;
                }
            }
            // Fallback tarif global de la ligne
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
     * ÉTAPE 1 : Enregistrement initial de la Réservation au statut PENDING (Pas de ticket émis).
     */
    public function createPendingReservation(Trip $trip, RouteStop $departure, RouteStop $arrival, BookingData $data, ?User $currentUser, \DateTimeImmutable $now): Reservation
    {
        // ◄ CORRECTION : On extrait le premier libellé de la liste (ex: "01")
        $seatsList = $data->seats;
        $seatLabel = !empty($seatsList) ? reset($seatsList) : null;

        if (null === $seatLabel) {
            throw new BookingException('Veuillez choisir un siège sur le plan de la cabine.');
        }

        /* $seatLabel = $data->seats[0] ?? null;
        if (null === $seatLabel) {
            throw new BookingException('Veuillez choisir un siège sur le plan de la cabine.');
        } */

        // Vérification de la disponibilité physique du siège [MCD 17]
        /*  $tripSeat = $this->em->getRepository(TripSeat::class)->findOneBy([
            'trip' => $trip,
            'seatLabel' => $seatLabel // Assure-toi que la propriété s'appelle seatLabel dans ton TripSeat
        ]);
        */
        // CHANGE CECI :
        // $tripSeat = $this->em->getRepository(TripSeat::class)->findOneBy([
        //     'trip' => $trip,
        //     'seatLabel' => $seatLabel
        // ]);

        // PAR CELA :
        $tripSeat = $this->em->getRepository(TripSeat::class)->createQueryBuilder('ts')
            ->join('ts.seat', 's')
            ->andWhere('ts.trip = :trip')
            ->andWhere('s.seatLabel = :seatLabel')
            ->setParameter('trip', $trip)
            ->setParameter('seatLabel', $seatLabel)
            ->getQuery()
            ->getOneOrNullResult();


        if (!$tripSeat || $tripSeat->getSeatStatus() !== TripSeatStatus::Available) {
            throw new BookingException(sprintf('Le siège %s n\'est plus disponible.', $seatLabel));
        }

        // 1. Découpage du fullName en Prénom et Nom d'après l'espace
        $parts = array_filter(explode(' ', trim((string) $data->fullName)));
        $firstname = array_shift($parts); // Premier mot = Prénom (ex: "Jean")
        $lastname = implode(' ', $parts);  // Mots restants = Nom (ex: "Mukendi")

        // 2. Enregistrement des coordonnées passager dans l'User [MCD 3]
        /** @var User $user */
        $user = $currentUser ?? new User();
        $user->setFirstname($firstname);
        $user->setLastname($lastname); // ◄ FIXE LE NOM : Plus aucune valeur NULL envoyée !
        $user->setPhone($data->phone);
        $user->setUserType(UserType::Client);
        $user->setIsActive(true);

        if (!$user->getId()) {
            $this->em->persist($user);
        }

        // Recalcul du montant certifié BDD pour contrer toute triche
        $quote = $this->quote($trip, $departure, $arrival, SeatType::Normal, $now);

        $reference = 'RSV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $reservation = (new Reservation())
            ->setReference($reference)
            ->setTrip($trip)
            ->setDepartureStop($departure)
            ->setArrivalStop($arrival)
            ->setAgency($trip->getRoute()?->getAgency())
            ->setUser($user)
            ->setStatus(ReservationStatus::Pending) // Reste PENDING selon ton MCD V1 [MCD 22]
            ->setTotalAmount($quote['amount'])
            ->setCurrency($quote['currency']);

        $this->em->persist($reservation);

        // Verrouillage temporaire du siège à bord (held pour 15 min) [MCD 17]
        $tripSeat->setSeatStatus(TripSeatStatus::Held);
        $tripSeat->setHeldUntil($now->modify('+15 minutes'));
        $tripSeat->setReservation($reservation);

        $this->em->flush();

        return $reservation;
    }

    /**
     * ÉTAPE 3 : Validation définitive de la réservation au comptoir de la gare (espèces).
     */
    public function confirmStationPayment(Reservation $reservation, \DateTimeImmutable $now): void
    {
        $tripSeat = $this->em->getRepository(TripSeat::class)->findOneBy(['reservation' => $reservation]);

        // Logique Documentaire V1 : Le statut reste PENDING (unpaid) mais la place passe en "held" figée
        $reservation->setStatus(ReservationStatus::Pending);

        $deadline = $this->stationDeadline($reservation, $now);
        $reservation->setExpiresAt($deadline); // Fixe l'échéance de carence des 2h [MCD 22]

        if ($tripSeat) {
            $tripSeat->setSeatStatus(TripSeatStatus::Held);
            $tripSeat->setHeldUntil($deadline); // Bloqué jusqu'à la limite des 2h de carence
        }

        $this->em->flush();
    }

    /**
     * Récupère une réservation PENDING active en BDD.
     */
    public function findPendingReservation(string $reference, \DateTimeImmutable $now): Reservation
    {
        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['reference' => $reference]);

        if (!$reservation) {
            throw new BookingException('Cette référence de réservation est introuvable.');
        }

        return $reservation;
    }

    /**
     * Recherche une réservation pour l'édition de pièces PDF.
     */
    public function findReservation(string $reference): ?Reservation
    {
        return $this->em->getRepository(Reservation::class)->findOneBy(['reference' => $reference]);
    }

    /**
     * Extrait l'état géométrique de chaque siège pour la grille interactive (Seatmap).
     */
    public function seatInfo(Trip $trip, RouteStop $departure, RouteStop $arrival, \DateTimeImmutable $now): array
    {
        $tripSeats = $this->em->getRepository(TripSeat::class)->findBy(['trip' => $trip]);
        $info = [];

        foreach ($tripSeats as $ts) {
            // ◄ ALIGNEMENT : On extrait l'objet Enum complet
            $statusEnum = $ts->getSeatStatus();

            // Si c'est un objet BackedEnum, on prend sa valeur brute (ex: "available", "held"), sinon on garde la chaîne
            $status = $statusEnum instanceof \BackedEnum ? $statusEnum->value : $statusEnum;

            // Logique de vérification de l'expiration du verrou temporaire
            if ($status === TripSeatStatus::Held->value && $ts->getHeldUntil() && $ts->getHeldUntil() < $now) {
                $status = TripSeatStatus::Available->value;
            }

            // ◄ SÉCURISATION : Le tableau ne contient plus que des chaînes de caractères pures
            $info[$ts->getSeat()?->getSeatLabel()] = $status;
        }

        return $info;
    }



    /**
     * Compile les entités brutes PostgreSQL dans ton DTO immuable BookingSummary.
     */
    public function summary(Reservation $reservation): BookingSummary
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
            status: (string) $reservation->getStatus(),
            tripCode: $trip?->getCode() ?? 'TRIP-UNK',
            routeLabel: $trip?->getRoute()?->getName() ?? 'Ligne Inconnue',
            agencyName: $reservation->getAgency()?->getName() ?? 'Agence Indéfinie',
            departureCity: $reservation->getDepartureStop()?->getCity() ?? '',
            arrivalCity: $reservation->getArrivalStop()?->getCity() ?? '',
            busModel: trim(($bus?->getBrand() ?? '') . ' ' . ($bus?->getModel() ?? 'Standard')),
            departureAt: $departureAt,
            expiresAt: $reservation->getExpiresAt(),
            passengerName: $user ? (string) $user->getFirstname() : 'Voyageur Anonyme',
            passengerPhone: $user ? (string) $user->getPhone() : '-',
            seats: [$seatLabel],
            fareCategory: $tripSeat && 'vip' === strtolower((string)$tripSeat->getSeat()?->getType()) ? 'VIP' : 'Standard',
            total: number_format((float) $reservation->getTotalAmount(), 2, ',', ' '),
            currencyCode: $reservation->getCurrency()?->getCode() ?? 'CDF'
        );
    }
}
