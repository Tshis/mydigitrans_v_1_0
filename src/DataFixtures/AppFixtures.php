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
            ->setEmail('admin@example.com')
            ->setPhone('+243810000000')
            ->setUserType(UserType::Platform)
            ->setCode('ADM-001')
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
         * 5b. CONFIGURATION DU BUS & GÉNÉRATION DES SIÈGES FIXES
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

        // ◄ INITIALISATION DU TABLEAU POUR COMPLÉTER LE MANQUE
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
                        ->setSeatLabel(sprintf('%d%s', $r, chr(64 + $c)))
                        ->setType($r <= 2 ? SeatType::Vip : SeatType::Normal)
                        ->setRow($r)
                        ->setColumn($c);

                    $manager->persist($seat);
                    $seats[] = $seat; // ◄ REMPLISSAGE DU TABLEAU : Règle l'erreur variable non reconnue
                }
            }
        }
        $manager->flush(); // Enregistre le bus et les sièges physiques

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
            ->setRoute($route);
        $manager->persist($stopLubumbashi);

        $stopLikasi = (new RouteStop())
            ->setCity('Likasi')
            ->setCountry('RDC')
            ->setStopOrder(2)
            ->setDurationFromDeparture(120)
            ->setDistanceFromDeparture('120.00')
            ->setIsActive(true)
            ->setRoute($route);
        $manager->persist($stopLikasi);

        $stopKolwezi = (new RouteStop())
            ->setCity('Kolwezi')
            ->setCountry('RDC')
            ->setStopOrder(3)
            ->setDurationFromDeparture(300)
            ->setDistanceFromDeparture('320.00')
            ->setIsActive(true)
            ->setRoute($route);
        $manager->persist($stopKolwezi);

        /*
         * 7. CONFIGURATION DES TARIFS (FARES)
         */
        $generalFare = (new Fare())
            ->setTargetType(FareTargetType::Route)
            ->setTargetId((int) $route->getId())
            ->setFareType(FareType::Standard)
            ->setAmount('35.00')
            ->setCurrency($usd)
            ->setIsActive(true)
            ->setValidFrom(new \DateTimeImmutable('-1 month'))
            ->setDepartureStop(null)
            ->setArrivalStop(null);
        $manager->persist($generalFare);

        $specificFare = (new Fare())
            ->setTargetType(FareTargetType::Route)
            ->setTargetId((int) $route->getId())
            ->setFareType(FareType::Standard)
            ->setAmount('15.00')
            ->setCurrency($usd)
            ->setIsActive(true)
            ->setValidFrom(new \DateTimeImmutable('-1 month'))
            ->setDepartureStop($stopLubumbashi)
            ->setArrivalStop($stopLikasi);
        $manager->persist($specificFare);

        $manager->flush();

        /*
         * 8. PLANIFICATION DE DEUX VOYAGES (TRIPS) SUR LA MÊME ROUTE (MATIN & APRÈS-MIDI)
         */
        $tripsData = [
            ['time' => '08:00:00', 'code' => 'TRIP-LUB-KOL-01'],
            ['time' => '14:00:00', 'code' => 'TRIP-LUB-KOL-02']
        ];

        foreach ($tripsData as $data) {
            $trip = (new Trip())
                ->setRoute($route)
                ->setBus($bus)
                ->setStatus(TripStatus::Scheduled)
                ->setDepartureDate(new \DateTimeImmutable('tomorrow'))
                ->setDepartureTime(new \DateTimeImmutable($data['time']))
                ->setCode($data['code'])
                ->setDriver($driver)
                ->setSlug(strtolower($data['code']));

            $manager->persist($trip);

            // Génération des TripSeats - Le tableau $seats contient à présent les 46 sièges réels !
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
