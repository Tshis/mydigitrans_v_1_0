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
         * 5. CONFIGURATION DU BUS, LAYOUT & SIÈGES FIXES
         */
        $layout = (new BusLayout())
            ->setName('Layout Standard Lushi')
            ->setAgency($agency)
            ->setRows(10)
            ->setColumns(4)
            ->setAisles(['2'])
            ->setSpecialPositions([])
            ->setHasBackExtraSeat(true)
            ->setCode('LYT-LUB-STD');
        $manager->persist($layout);

        $bus = (new Bus())
            ->setAgency($agency)
            ->setBusLayout($layout)
            ->setCode('BUS-LUB-01')
            ->setName('Bus Interurbain Lubumbashi')
            ->setPlateNumber('KV-4321-RDC')
            ->setBrand('Mercedes-Benz')
            ->setModel('Tourismo')
            ->setStatus(BusStatus::Active)
            ->setMileage(45000)
            ->setYear(2023)
            ->setCreatedBy($admin);
        $manager->persist($bus);

        $seats = [];
        for ($r = 1; $r <= 5; $r++) { // 20 sièges générés pour le test
            for ($c = 1; $c <= 4; $c++) {
                $seat = (new Seat())
                    ->setBus($bus)
                    ->setSeatLabel(chr(64 + $c) . $r)
                    ->setType($r === 1 ? SeatType::Vip : SeatType::Normal)
                    ->setRow($r)
                    ->setColumn($c);
                $manager->persist($seat);
                $seats[] = $seat;
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
            ->setCreatedBy($admin); // ◄ CORRECTION : Ajout du créateur obligatoire
        $manager->persist($route);

        $stopLubumbashi = (new RouteStop())
            ->setCity('Lubumbashi')
            ->setCountry('RDC') // ◄ CORRECTION : Pays obligatoire
            ->setStopOrder(1)
            ->setDurationFromDeparture(0)
            ->setDistanceFromDeparture('0.00') // ◄ CORRECTION : Point de départ
            ->setIsActive(true)
            ->setCreatedBy($admin)
            ->setRoute($route);
        $manager->persist($stopLubumbashi);

        $stopLikasi = (new RouteStop())
            ->setCity('Likasi')
            ->setCountry('RDC') // ◄ CORRECTION : Pays obligatoire
            ->setStopOrder(2)
            ->setDurationFromDeparture(120) // +2h
            ->setDistanceFromDeparture('120') // ◄ CORRECTION : Point de départ
            ->setIsActive(true)
            ->setCreatedBy($admin)
            ->setRoute($route);
        $manager->persist($stopLikasi);

        $stopKolwezi = (new RouteStop())
            ->setCity('Kolwezi')
            ->setStopOrder(3)
            ->setCountry('RDC') // ◄ CORRECTION : Pays obligatoire
            ->setDurationFromDeparture(300) // +5h
            ->setDistanceFromDeparture('356') // ◄ CORRECTION : Point de départ
            ->setIsActive(true)
            ->setCreatedBy($admin)
            ->setRoute($route);
        $manager->persist($stopKolwezi);

        $manager->flush();

        /*
         * 7. CONFIGURATION DES TARIFS (FARES) LIÉS AUX DEVISES
         */
        // Tarif Général Ligne Complète (Fallback)
        $generalFare = (new Fare())
            ->setTargetType(FareTargetType::Route)
            ->setTargetId($route->getId())
            ->setFareType(FareType::Standard)
            ->setAmount('35.00') // 35$
            ->setCurrency($usd) // Reçoit l'objet Currency complet
            ->setIsActive(true)
            ->setValidFrom(new \DateTimeImmutable('-1 month'))
            ->setDepartureStop(null)
            ->setCreatedBy($admin)
            ->setArrivalStop(null);
        $manager->persist($generalFare);

        // Tarif Spécifique Tronçon Court (Lubumbashi -> Likasi)
        $specificFare = (new Fare())
            ->setTargetType(FareTargetType::Route)
            ->setTargetId($route->getId())
            ->setFareType(FareType::Standard)
            ->setAmount('15.00') // 15$
            ->setCurrency($usd) // Reçoit l'objet Currency complet
            ->setIsActive(true)
            ->setValidFrom(new \DateTimeImmutable('-1 month'))
            ->setDepartureStop($stopLubumbashi)
            ->setCreatedBy($admin)
            ->setArrivalStop($stopLikasi);
        $manager->persist($specificFare);

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
                ->setDepartureDate(new \DateTimeImmutable('tomorrow')) // Date identique (demain)
                ->setDepartureTime(new \DateTimeImmutable($data['time'])) // Heures différentes
                ->setArrivalDate(new \DateTimeImmutable('tomorrow')) // Date identique (demain)
                ->setArrivalTime(new \DateTimeImmutable($data['time'])) // Heures différentes
                ->setCode($data['code'])
                ->setSlug('trip-' . $data["code"])
                ->SetCreatedBy($admin)
                ->setDriver($driver);
            $manager->persist($trip);

            // Génération de l'état des sièges vendables pour chaque trajet (TripSeat)
            foreach ($seats as $seat) {
                $tripSeat = (new TripSeat())
                    ->setTrip($trip)
                    ->setSeat($seat)
                    ->setSeatStatus(TripSeatStatus::Available);
                $manager->persist($tripSeat);
            }
        }

        $manager->flush();
    }
}
