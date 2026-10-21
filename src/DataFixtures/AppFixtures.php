<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Agency;
use App\Entity\Bus;
use App\Entity\BusLayout;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\Fare;
use App\Entity\Role;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Seat;
use App\Entity\Trip;
use App\Entity\TripSeat;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\BusStatus;
use App\Enum\FareTargetType;
use App\Enum\FareType;
use App\Enum\Scope;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use App\Enum\TripStatus;
use App\Enum\UserType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        /*
         * 1. CRÉATION DE L'ADMINISTRATEUR PLATEFORME
         */
        $admin = (new User())
            ->setFirstname('Super')
            ->setLastname('Admin')
            ->setEmail('admin1@example.com')
            ->setPhone('+243810000000')
            ->setUserType(UserType::Platform)
            ->setCode('ADM-0011')
            ->setIsActive(true)
            ->setIsOnline(false);

        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Password123!'));
        $manager->persist($admin);
        $manager->flush(); // Requis pour lier created_by_id sur les devises

        /*
         * 2. CRÉATION DES DEVISES COHÉRENTES
         */
        $usd = (new Currency())
            ->setCode('USD')
            ->setName('Dollar Américain')
            ->setCreatedBy($admin);
        $manager->persist($usd);

        $cdf = (new Currency())
            ->setCode('CDF')
            ->setName('Franc Congolais')
            ->setCreatedBy($admin);
        $manager->persist($cdf);

        $manager->flush();

        /*
         * 3. CRÉATION DE L'AGENCE LUBUMBASHI
         */
        $agency = (new Agency())
            ->setName('Agence Lubumbashi')
            ->setCode('AG-0001')
            ->setSlug('lubumbashi')
            ->setIsActive(true)
            ->setCountry('RDC')
            ->setCity('Lubumbashi')
            ->setIsVerified(true)
            ->setDefaultCurrency($usd)
            ->setPhone('+243812345678')
            ->setEmail('contact.lubumbashi@mydigitrans.com')
            ->setAddress('Avenue des Transports, Centre-ville Lubumbashi');
        $manager->persist($agency);

        // Liaison Devise Agence
        $currencyAgency = (new CurrencyAgency())
            ->setAgency($agency)
            ->setCurrency($usd)
            ->setIsBase(true)
            ->setIsActive(true);
        $manager->persist($currencyAgency);
        $manager->flush();


        // ... (Fin de la création de l'agence Lubumbashi et du CurrencyAgency) ...

        $manager->persist($currencyAgency);
        $manager->flush();

        /*
         * 3b. NOUVEAU - CRÉATION DES SUCCURSALES (BRANCHES) REQUISES POUR LES COLIS
         */
        $branchLubumbashi = (new \App\Entity\Branch())
            ->setName('Dépôt Central Lubumbashi')
            ->setCode('BR-LUB-001')
            ->setCity('Lubumbashi')
            ->setCountry('RDC')
            ->setStatus(\App\Enum\BranchStatus::Opened) // REQUIS : Doit être Opened
            ->setSlug('depot-central-lubumbashi')
            ->setAgency($agency);

        $manager->persist($branchLubumbashi);

        $branchKolwezi = (new \App\Entity\Branch())
            ->setName('Guichet Retrait Kolwezi')
            ->setCode('BR-KOL-001')
            ->setCity('Kolwezi')
            ->setCountry('RDC')
            ->setStatus(\App\Enum\BranchStatus::Opened) // REQUIS : Doit être Opened
            ->setSlug('guichet-retrait-kolwezi')
            ->setAgency($agency);
        $manager->persist($branchKolwezi);

        $manager->flush();


        /*
         * 4. CONFIGURATION CONDUCTEUR
         */
        $driverRole = (new Role())
            ->setName('Conducteur')
            ->setCode('ROLE_DRIVER')
            ->setScope(Scope::Agency)
            ->setDescription('Conducteur de bus')
            ->setIsActive(true);
        $manager->persist($driverRole);

        $driver = (new User())
            ->setFirstname('Jean')
            ->setLastname('Conducteur Lushi')
            ->setEmail('driver.lushi@example.com')
            ->setPhone('+243830000001')
            ->setUserType(UserType::Agency)
            ->setCode('DRV-LUB-01')
            ->setAgency($agency)
            ->setIsActive(true)
            ->setIsOnline(false);
        $driver->setPassword($this->passwordHasher->hashPassword($driver, 'Password123!'));
        $manager->persist($driver);

        $userRole = (new UserRole())
            ->setUser($driver)
            ->setRole($driverRole)
            ->setAgency($agency)
            ->setAssignedBy($admin);
        $manager->persist($userRole);
        $manager->flush();

        /*
         * 5. CONFIGURATION DU BUS LAYOUT (YUTONG 50 PLACES)
         */
        $specialPositions = [
            ["type" => "driver", "row" => 1, "col" => 1],
            ["type" => "aisle", "row" => 1, "col" => 2],
            ["type" => "door", "row" => 2, "col" => 4],
            ["type" => "aisle", "row" => 2, "col" => 3]
        ];

        $layout = (new BusLayout())
            ->setName('Yutong 50 places modele 3-2')
            ->setAgency($agency)
            ->setRows(11)
            ->setColumns(4)
            ->setAisles(['2'])
            ->setHasBackExtraSeat(true)
            ->setCode('LYT-YUT-50-VIP');

        $cleanPositions = json_decode(json_encode($specialPositions), true);
        $layout->setSpecialPositions($cleanPositions);

        $manager->persist($layout);
        $manager->flush(); // On fige le layout pour stabiliser le JSON


        /*
         * 5b. CONFIGURATION DU BUS & GÉNÉRATION DES SIÈGES FIXES (NUMÉROTATION SIMPLE)
         */
        $bus = (new Bus())
            ->setAgency($agency)
            ->setBusLayout($layout)
            ->setCode('BUS-LUB-YUT-01')
            ->setName('Yutong Premium Confort')
            ->setPlateNumber('KV-7890-RDC')
            ->setBrand('Yutong')
            ->setModel('Modèle 50 places 3-2 VIP')
            ->setStatus(BusStatus::Active)
            ->setMileage(12000)
            ->setYear(2025)
            ->setCreatedBy($admin);
        $manager->persist($bus);

        $staticPositions = [
            ["row" => 1, "col" => 1],
            ["row" => 1, "col" => 2],
            ["row" => 2, "col" => 4],
            ["row" => 2, "col" => 3]
        ];

        // ◄ COMPTEUR DE SIÈGE UNIQUE POUR NUMÉROTATION SEQUENTIELLE (1, 2, 3...)
        $seatSequenceNumber = 1;
        $seats = [];

        for ($r = 1; $r <= 11; $r++) {
            for ($c = 1; $c <= 4; $c++) {
                $isSpecial = false;
                foreach ($staticPositions as $pos) {
                    if ($pos['row'] === $r && $pos['col'] === $c) {
                        $isSpecial = true;
                        break;
                    }
                }

                if (!$isSpecial) {
                    $seat = (new Seat())
                        ->setBus($bus)
                        ->setSeatLabel((string) $seatSequenceNumber) // ◄ ENREGISTRE "1", "2", "3", "5" EN BDD
                        ->setType($r <= 2 ? SeatType::Vip : SeatType::Normal)
                        ->setRow($r)
                        ->setColumn($c);

                    $manager->persist($seat);
                    $seats[] = $seat;

                    $seatSequenceNumber++; // Incrémentation continue
                }
            }
        }
        $manager->flush();



        /*
         * 6. CRÉATION DE LA ROUTE & DES ARRÊTS (STOPS)
         */
        $route = (new Route())
            ->setName('Lubumbashi - Kolwezi via Likasi')
            ->setAgency($agency)
            ->setIsAvailable(true)
            ->setSlug('lubumbashi-kolwezi')
            ->setCode('RT-LUB-001')
            ->setDistance('320.00')
            ->setDuration(300)
            ->setCreatedBy($admin);
        $manager->persist($route);

        $stopLubumbashi = (new RouteStop())
            ->setCity('Lubumbashi')
            ->setCountry('RDC')
            ->setStopOrder(1)
            ->setDurationFromDeparture(0)
            ->setDistanceFromDeparture('0.00')
            ->setIsActive(true)
            ->setRoute($route)
            ->setCreatedBy($admin);
        $manager->persist($stopLubumbashi);

        $stopLikasi = (new RouteStop())
            ->setCity('Likasi')
            ->setCountry('RDC')
            ->setStopOrder(2)
            ->setDurationFromDeparture(120)
            ->setDistanceFromDeparture('120.00')
            ->setIsActive(true)
            ->setRoute($route)
            ->setCreatedBy($admin);
        $manager->persist($stopLikasi);

        $stopKolwezi = (new RouteStop())
            ->setCity('Kolwezi')
            ->setCountry('RDC')
            ->setStopOrder(3)
            ->setDurationFromDeparture(300)
            ->setDistanceFromDeparture('320.00')
            ->setIsActive(true)
            ->setRoute($route)
            ->setCreatedBy($admin);
        $manager->persist($stopKolwezi);

        // ◄ IMPÉRATIF : On pousse en BDD pour générer physiquement les IDs de la route et des arrêts
        $manager->flush();

        /*
         * 7. CONFIGURATION DES TARIFS (FARES) EN CDF
         */
        // Tarif Général : Lubumbashi - Kolwezi (35 000 FC)
        $generalFare = (new Fare())
            ->setTargetType(FareTargetType::Route)
            ->setTargetId((int) $route->getId()) // ◄ ALIGNEMENT TYPE : Forcé en (int) et ID enfin disponible
            ->setFareType(FareType::Standard)
            ->setAmount("35000.00")
            ->setCurrency($cdf)
            ->setIsActive(true)
            ->setValidFrom(new \DateTimeImmutable('-1 month'))
            ->setDepartureStop(null)
            ->setArrivalStop(null)
            ->setCreatedBy($admin);
        $manager->persist($generalFare);

        // Tarif Tronçon 1 : Lubumbashi - Likasi (15 000 FC)
        $fareLshiLikasi = (new Fare())
            ->setTargetType(FareTargetType::Route)
            ->setTargetId((int) $route->getId()) // ◄ ALIGNEMENT TYPE
            ->setFareType(FareType::Standard)
            ->setAmount("15000.00")
            ->setCurrency($cdf)
            ->setIsActive(true)
            ->setValidFrom(new \DateTimeImmutable('-1 month'))
            ->setDepartureStop($stopLubumbashi)
            ->setArrivalStop($stopLikasi)
            ->setCreatedBy($admin);
        $manager->persist($fareLshiLikasi);

        // Tarif Tronçon 2 : Likasi - Kolwezi (25 000 FC)
        $fareLikasiKzi = (new Fare())
            ->setTargetType(FareTargetType::Route)
            ->setTargetId((int) $route->getId()) // ◄ ALIGNEMENT TYPE
            ->setFareType(FareType::Standard)
            ->setAmount("25000.00")
            ->setCurrency($cdf)
            ->setIsActive(true)
            ->setValidFrom(new \DateTimeImmutable('-1 month'))
            ->setDepartureStop($stopLikasi)
            ->setArrivalStop($stopKolwezi)
            ->setCreatedBy($admin);
        $manager->persist($fareLikasiKzi);



        /*
         * 8. PLANIFICATION DE DEUX VOYAGES (TRIPS) SUR LA MÊME ROUTE (MATIN & APRÈS-MIDI)
         */
        $tripsData = [
            ['time' => '08:00:00', 'code' => 'TRIP-LUB-KOL-01'],
            ['time' => '14:00:00', 'code' => 'TRIP-LUB-KOL-02']
        ];

        foreach ($tripsData as $data) {
            $departureDate = new \DateTimeImmutable('tomorrow');
            $departureTime = new \DateTimeImmutable($data['time']);

            // Calcul précis de l'arrivée : Départ + 5 heures (300 minutes de la route)
            $departureAt = $departureDate->setTime((int)$departureTime->format('H'), (int)$departureTime->format('i'), 0);
            $arrivalAt = $departureAt->modify('+5 hours');

            $trip = (new Trip())
                ->setRoute($route)
                ->setBus($bus)
                ->setStatus(TripStatus::Scheduled)
                ->setDepartureDate($departureDate)
                ->setDepartureTime($departureTime)

                // ◄ AJOUT : Alignement sur les contraintes NOT NULL de ton entité Trip
                ->setArrivalDate(\DateTimeImmutable::createFromInterface($arrivalAt))
                ->setArrivalTime(\DateTimeImmutable::createFromInterface($arrivalAt))

                ->setCode($data['code'])
                ->setDriver($driver)
                ->setSlug(strtolower($data['code']))
                ->setCreatedBy($admin);

            $manager->persist($trip);

            // Génération des TripSeats pour chaque voyage
            foreach ($seats as $seat) {
                $tripSeat = (new TripSeat())
                    ->setTrip($trip)
                    ->setSeat($seat)
                    ->setSeatStatus(TripSeatStatus::Available);
                $manager->persist($tripSeat);
            }
        }

        $manager->flush();
        $manager->clear();
    }
}
