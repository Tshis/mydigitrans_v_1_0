<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\TripPlanningData;
use App\Entity\Agency;
use App\Entity\Trip;
use App\Entity\TripSeat;
use App\Entity\User;
use App\Enum\BusStatus;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use App\Enum\TripStatus;
use App\Exception\TripPlanningException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Planification d'un voyage : crée le Trip puis, en cascade, un TripSeat par siège du bus
 * (le conducteur et les WC sont bloqués d'office), dans une seule transaction.
 */
final class TripPlanningService
{
    /** Rôle (portée « agence ») des chauffeurs : un utilisateur qui l'a, non révoqué, peut être affecté à un voyage. */
    public const DRIVER_ROLE_CODE = 'ROLE_DRIVER';

    /** Temps minimum de rotation/repos (en minutes) entre l'arrivée d'un bus et son prochain départ. */
    private const TURNAROUND_MINUTES = 120;


    /** Un bus ou un chauffeur déjà affecté à un voyage dans l'un de ces statuts n'est plus disponible. */
    private const BUSY_STATUSES = [TripStatus::Scheduled, TripStatus::Boarding, TripStatus::Departed];

    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @throws TripPlanningException
     */
    public function plan(TripPlanningData $data, User $creator): Trip
    {
        return $this->em->wrapInTransaction(function () use ($data, $creator): Trip {
            $agency = $creator->getAgency() ?? throw new TripPlanningException([TripPlanningException::GLOBAL => 'Votre compte n\'est rattaché à aucune agence.']);
            $route = $data->route;
            $bus = $data->bus;
            $driver = $data->driver;
            $departureAt = $data->departureAt();
            $arrivalAt = $data->arrivalAt();

            if (null === $route || null === $bus || null === $driver || null === $departureAt || null === $arrivalAt) {
                throw new TripPlanningException([TripPlanningException::GLOBAL => 'Le formulaire est incomplet.']);
            }

            $errors = [];

            // Défense en profondeur : le formulaire ne propose déjà que les éléments de l'agence.
            if ($route->getAgency()?->getId() !== $agency->getId()) {
                $errors['route_id'] = 'Cette ligne n\'appartient pas à votre agence.';
            }
            if ($bus->getAgency()?->getId() !== $agency->getId()) {
                $errors['bus_id'] = 'Ce bus n\'appartient pas à votre agence.';
            }

            if (BusStatus::Active !== $bus->getStatus()) {
                $errors['bus_id'] = 'Ce bus n\'est pas actif et ne peut pas être planifié.';
            }


            $errors += $this->conflicts($data, $departureAt, $arrivalAt);

            if ($bus->getSeats()->isEmpty()) {
                $errors['bus_id'] ??= 'Ce bus n\'a aucun siège : le gabarit du bus doit d\'abord générer ses sièges.';
            }

            if (null !== $data->departureBranch && $data->departureBranch->getAgency()?->getId() !== $agency->getId()) {
                $errors['departureBranch'] = 'Cette gare d\'embarquement n\'appartient pas à votre agence.';
            }
            if (null !== $data->arrivalBranch && $data->arrivalBranch->getAgency()?->getId() !== $agency->getId()) {
                $errors['arrivalBranch'] = 'Cette gare de débarquement n\'appartient pas à votre agence.';
            }


            if ([] !== $errors) {
                throw new TripPlanningException($errors);
            }

            $trip = (new Trip())
                ->setRoute($route)
                ->setBus($bus)
                ->setDepartureDate($data->departureDate)
                ->setDepartureTime($data->departureTime)
                ->setArrivalDate($data->arrivalDate)
                ->setArrivalTime($data->arrivalTime)
                ->setDriver($driver)
                ->setCoDriver($data->coDriver)
                ->setDepartureBranch($data->departureBranch)
                ->setArrivalBranch($data->arrivalBranch)
                ->setStatus(TripStatus::Scheduled)
                ->setCode($this->nextCode($agency, $departureAt))
                ->setSlug($this->uniqueSlug((string) $route->getName(), $departureAt))
                ->setCreatedBy($creator);

            $this->em->persist($trip);

            // Génération en cascade : un siège de voyage par siège du bus, jamais deux fois le même (unicité trip + seat).
            foreach ($bus->getSeats() as $seat) {
                $blocked = \in_array($seat->getType(), [SeatType::Driver, SeatType::Wc], true);

                $tripSeat = (new TripSeat())
                    ->setSeat($seat)
                    ->setSeatStatus($blocked ? TripSeatStatus::Blocked : TripSeatStatus::Available);
                $trip->addTripSeat($tripSeat);
                $this->em->persist($tripSeat);
            }

            return $trip;
        });
    }

    /**
     * Le bus, le chauffeur et le convoyeur ne peuvent pas être sur deux voyages qui se chevauchent.
     *
     * @return array<string, string> champ => message
     */
    /**
     * Le bus, le chauffeur et le convoyeur ne peuvent pas être sur deux voyages qui se chevauchent,
     * et le bus doit repartir de la ville où il est arrivé lors de son voyage précédent.
     *
     * @return array<string, string> champ => message
     */
    private function conflicts(TripPlanningData $data, \DateTimeImmutable $departureAt, \DateTimeImmutable $arrivalAt): array
    {
        $people = array_values(array_filter([$data->driver?->getId(), $data->coDriver?->getId()]));

        /** @var list<Trip> $candidates */
        $candidates = $this->em->createQueryBuilder()
            ->select('t', 'r')
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->andWhere('t.status IN (:statuses)')
            ->andWhere('t.departureDate <= :to')
            ->andWhere('t.arrivalDate >= :from')
            ->andWhere('(IDENTITY(t.bus) = :bus OR IDENTITY(t.driver) IN (:people) OR IDENTITY(t.coDriver) IN (:people))')
            ->setParameter('statuses', array_map(static fn(TripStatus $s): string => $s->value, self::BUSY_STATUSES))
            ->setParameter('from', $departureAt->modify('-3 days'), Types::DATE_IMMUTABLE)
            ->setParameter('to', $arrivalAt->modify('+3 days'), Types::DATE_IMMUTABLE)
            ->setParameter('bus', $data->bus?->getId())
            ->setParameter('people', [] === $people ? [0] : $people)
            ->getQuery()
            ->getResult();

        $errors = [];
        $buffer = sprintf('+%d minutes', self::TURNAROUND_MINUTES);
        [$newOriginCity,] = $this->routeEndpoints($data->route);

        $latestPreviousTripForBus = null;
        $latestPreviousArrival = null;

        foreach ($candidates as $other) {
            $otherDeparture = $this->at($other->getDepartureDate(), $other->getDepartureTime());
            $otherArrival   = $this->at($other->getArrivalDate(), $other->getArrivalTime());

            if (null === $otherDeparture || null === $otherArrival) {
                continue;
            }

            $code = (string) $other->getCode();
            $isSameBus = $other->getBus()?->getId() === $data->bus?->getId();

            // 1. Repérer le dernier voyage du bus qui se termine AVANT le nouveau départ (pour vérifier la ville)
            if ($isSameBus && $otherArrival <= $departureAt) {
                if (null === $latestPreviousArrival || $otherArrival > $latestPreviousArrival) {
                    $latestPreviousArrival = $otherArrival;
                    $latestPreviousTripForBus = $other;
                }
            }

            // 2. Vérifier le chevauchement horaire + temps de battement (120 min)
            $otherFreeAt = $otherArrival->modify($buffer);
            $newFreeAt   = $arrivalAt->modify($buffer);

            if ($otherDeparture >= $newFreeAt || $otherFreeAt <= $departureAt) {
                continue;
            }

            if ($isSameBus) {
                $errors['bus_id'] ??= sprintf(
                    'Ce bus est occupé par le voyage %s (arrivée le %s, disponible à partir de %s).',
                    $code,
                    $otherArrival->format('d/m à H\hi'),
                    $otherFreeAt->format('d/m à H\hi')
                );
            }

            $crew = [$other->getDriver()?->getId(), $other->getCoDriver()?->getId()];

            if (\in_array($data->driver?->getId(), $crew, true)) {
                $errors['driver_id'] ??= sprintf('Ce chauffeur est déjà affecté au voyage %s sur cette période.', $code);
            }
            if (null !== $data->coDriver && \in_array($data->coDriver->getId(), $crew, true)) {
                $errors['codriver_id'] ??= sprintf('Ce convoyeur est déjà affecté au voyage %s sur cette période.', $code);
            }
        }

        // 3. Vérification géographique : le bus doit repartir de la ville où il est arrivé !
        if (null !== $latestPreviousTripForBus && null !== $newOriginCity && !isset($errors['bus_id'])) {
            [, $previousDestinationCity] = $this->routeEndpoints($latestPreviousTripForBus->getRoute());

            if (
                null !== $previousDestinationCity
                && mb_strtolower($previousDestinationCity) !== mb_strtolower($newOriginCity)
            ) {
                $errors['bus_id'] = sprintf(
                    'Incohérence géographique : ce bus arrivera à %s le %s (voyage %s) et ne peut pas repartir directement depuis %s.',
                    $previousDestinationCity,
                    $latestPreviousArrival->format('d/m/Y à H\hi'),
                    (string) $latestPreviousTripForBus->getCode(),
                    $newOriginCity
                );
            }
        }

        // 4. Vérification géographique symétrique : le bus doit arriver dans la ville d'où part son prochain voyage déjà planifié !
        $earliestNextTripForBus = null;
        $earliestNextDeparture = null;

        foreach ($candidates as $other) {
            if ($other->getBus()?->getId() !== $data->bus?->getId()) {
                continue;
            }
            $otherDep = $this->at($other->getDepartureDate(), $other->getDepartureTime());
            if (null !== $otherDep && $otherDep >= $arrivalAt) {
                if (null === $earliestNextDeparture || $otherDep < $earliestNextDeparture) {
                    $earliestNextDeparture = $otherDep;
                    $earliestNextTripForBus = $other;
                }
            }
        }

        [, $newDestinationCity] = $this->routeEndpoints($data->route);
        if (null !== $earliestNextTripForBus && null !== $newDestinationCity && !isset($errors['bus_id'])) {
            [$nextOriginCity,] = $this->routeEndpoints($earliestNextTripForBus->getRoute());

            if (null !== $nextOriginCity && mb_strtolower($nextOriginCity) !== mb_strtolower($newDestinationCity)) {
                $errors['bus_id'] = sprintf(
                    'Incohérence géographique : ce bus doit repartir de %s le %s (voyage %s) et ne peut pas terminer ce trajet à %s.',
                    $nextOriginCity,
                    $earliestNextDeparture->format('d/m/Y à H\hi'),
                    (string) $earliestNextTripForBus->getCode(),
                    $newDestinationCity
                );
            }
        }


        return $errors;
    }

    /**
     * Retourne [ville de départ, ville d'arrivée finale] d'une route d'après ses RouteStops actifs.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function routeEndpoints(?\App\Entity\Route $route): array
    {
        if (null === $route) {
            return [null, null];
        }

        $stops = [];
        foreach ($route->getRouteStops() as $stop) {
            if ($stop->isActive()) {
                $stops[] = $stop;
            }
        }

        if ([] === $stops) {
            return [null, null];
        }

        usort($stops, static fn($a, $b): int => $a->getStopOrder() <=> $b->getStopOrder());

        $firstCity = trim((string) reset($stops)->getCity());
        $lastCity  = trim((string) end($stops)->getCity());

        return ['' !== $firstCity ? $firstCity : null, '' !== $lastCity ? $lastCity : null];
    }


    private function at(?\DateTimeInterface $date, ?\DateTimeInterface $time): ?\DateTimeImmutable
    {
        if (null === $date || null === $time) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($date)->setTime((int) $time->format('H'), (int) $time->format('i'));
    }

    /**
     * Code du voyage : trip-{mois}-{année}-{numéro du mois dans l'agence}-{agence}, par exemple trip-07-26-0001-01.
     */
    private function nextCode(Agency $agency, \DateTimeImmutable $departureAt): string
    {
        $monthStart = $departureAt->modify('first day of this month')->setTime(0, 0);

        $sequence = 1 + (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->andWhere('r.agency = :agency')
            ->andWhere('t.departureDate >= :start')
            ->andWhere('t.departureDate <= :end')
            ->setParameter('agency', $agency)
            ->setParameter('start', $monthStart, Types::DATE_IMMUTABLE)
            ->setParameter('end', $monthStart->modify('last day of this month'), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();

        $repository = $this->em->getRepository(Trip::class);

        do {
            $code = sprintf('trip-%s-%s-%04d-%02d', $departureAt->format('m'), $departureAt->format('y'), $sequence++, (int) $agency->getId());
        } while (null !== $repository->findOneBy(['code' => $code]));

        return $code;
    }

    /**
     * Slug public du voyage (utilisé par la réservation en ligne) : ligne, date et suffixe aléatoire.
     */
    private function uniqueSlug(string $routeName, \DateTimeImmutable $departureAt): string
    {
        $base = substr((new AsciiSlugger())->slug($routeName)->lower()->toString() ?: 'voyage', 0, 100);
        $repository = $this->em->getRepository(Trip::class);

        do {
            $slug = sprintf('%s-%s-%s', $base, $departureAt->format('Y-m-d'), bin2hex(random_bytes(3)));
        } while (null !== $repository->findOneBy(['slug' => $slug]));

        return $slug;
    }
}
