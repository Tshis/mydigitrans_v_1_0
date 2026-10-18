<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Agency;
use App\Entity\Bus;
use App\Entity\BusLayout;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\Role;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Seat;
use App\Entity\Trip;
use App\Entity\TripSeat;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\BusStatus;
use App\Enum\Scope;
use App\Enum\SeatType;
use App\Enum\TripSeatStatus;
use App\Enum\TripStatus;
use App\Enum\UserType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TripDataFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {

        /*
         * 1. CRÉATION DE L'ADMINISTRATEUR PLATEFORME EN PREMIER
         * (Nécessaire car Currency a besoin d'un User pour sa colonne created_by_id)
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
        $manager->flush(); // Flush obligatoire pour générer l'ID de l'admin en base

        /*
         * 2. CRÉATION DES DEVISES (Avec assignation du créateur)
         */
        $usd = (new Currency())
            ->setCode('USD')
            ->setName('Dollar Américain')
            ->setCreatedBy($admin); // CORRECTION : Reçoit l'objet User
        $manager->persist($usd);

        $cdf = (new Currency())
            ->setCode('CDF')
            ->setName('Franc Congolais')
            ->setCreatedBy($admin); // CORRECTION : Reçoit l'objet User
        $manager->persist($cdf);

        $manager->flush();


        /*
         * 3. CRÉATION DES AGENCES (MCD 1) & DEVISES AGENCES (MCD 19)
         */
        $agenciesCodes = [
            'AG-0001' => 'Lubumbashi',
            'AG-0002' => 'Kinshasa',
            'AG-0003' => 'Kolwezi'
        ];
        $agencies = [];

        foreach ($agenciesCodes as $code => $name) {
            $agency = (new Agency())
                ->setName('Agence ' . $name)
                ->setCode($code)
                ->setSlug(strtolower($name))
                ->setIsActive(true)
                ->setCountry('RDC')
                ->setCity($name)
                ->setIsVerified(true)
                ->setDefaultCurrency($usd)
                ->setPhone(sprintf('+24381%d00000', rand(10, 99))) // CORRECTION : Ajout du téléphone requis
                ->setEmail(strtolower(sprintf('contact.%s@mydigitrans.com', $name))) // Sécurité MCD
                ->setAddress(sprintf('Avenue des Transports, Centre-ville %s', $name)); // Sécurité MCD

            $manager->persist($agency);
            $agencies[strtolower($name)] = $agency;

            // Liaison Devise / Agence
            $currencyAgency = (new CurrencyAgency())
                ->setAgency($agency)
                ->setCurrency($usd)
                ->setIsBase(true)
                ->setIsActive(true);
            $manager->persist($currencyAgency);
        }
        $manager->flush();


        /*
         * 4. CRÉATION DU RÔLE CONDUCTEUR
         */
        $driverRole = (new Role())
            ->setName('Conducteur')
            ->setCode('ROLE_DRIVER')
            ->setScope(Scope::Agency)
            ->setDescription('Conducteur de bus')
            ->setIsActive(true);
        $manager->persist($driverRole);
        $manager->flush();

        /*
         * 5. CRÉATION DES CONDUCTEURS (MCD 3)
         */
        $drivers = [];
        foreach ($agencies as $agencyKey => $agency) {
            $drivers[$agencyKey] = [];

            for ($i = 1; $i <= 2; ++$i) {
                $email = sprintf('driver.%s.%d@example.com', $agencyKey, $i);

                $driver = (new User())
                    ->setFirstname($i === 1 ? 'Jean' : 'Paul')
                    ->setLastname(sprintf('Conducteur %s %d', $agencyKey, $i))
                    ->setEmail($email)
                    ->setPhone(sprintf('+243830%06d', $i + (array_search($agencyKey, array_keys($agencies), true) * 10)))
                    ->setUserType(UserType::Agency)
                    ->setCode(sprintf('DRV-%s-%02d', strtoupper(substr($agencyKey, 0, 3)), $i))
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

                $drivers[$agencyKey][] = $driver;
            }
        }
        $manager->flush();

        /*
         * 6. CONFIGURATION DES BUS LAYOUTS, BUS & SIÈGES (MCD 8, 9, 13)
         */
        $buses = [];
        foreach ($agencies as $agencyKey => $agency) {
            $layout = (new BusLayout())
                ->setName(sprintf('Layout Standard %s', ucfirst($agencyKey)))
                ->setAgency($agency)
                ->setRows(10)
                ->setColumns(4)
                ->setAisles(['2'])
                ->setSpecialPositions([])
                ->setHasBackExtraSeat(true)
                ->setCode(sprintf('LYT-%s-STD', strtoupper(substr($agencyKey, 0, 3))));
            $manager->persist($layout);

            $bus = (new Bus())
                ->setAgency($agency)
                ->setBusLayout($layout)
                ->setCode(sprintf('BUS-%s-01', strtoupper(substr($agencyKey, 0, 3))))
                ->setName(sprintf('Bus Interurbain %s', ucfirst($agencyKey)))
                ->setPlateNumber(sprintf('KV-%d-RDC', rand(1000, 9999)))
                ->setBrand('Mercedes-Benz')
                ->setModel('Tourismo')
                ->setStatus(BusStatus::Active) // Première lettre en Majuscule
                ->setMileage(45000)
                ->setYear(2023)
                ->setCreatedBy($admin); // Reçoit l'objet User
            $manager->persist($bus);
            $buses[$agencyKey] = $bus;

            // Génération des places fixes du Bus (MCD 13)
            for ($r = 1; $r <= 10; $r++) {
                for ($c = 1; $c <= 4; $c++) {
                    $seat = (new Seat())
                        ->setBus($bus)
                        ->setSeatLabel(chr(64 + $c) . $r)
                        ->setType($r === 1 ? SeatType::Vip : SeatType::Normal) // Première lettre en Majuscule
                        ->setRow($r)
                        ->setColumn($c);
                    $manager->persist($seat);
                }
            }
        }
        $manager->flush();

        /*
         * 7. CONFIGURATION DES ROUTES, STOPS, VOYAGES & TRIPSEATS (MCD 14, 15, 16, 17)
         */
        $routesData = [
            'lubumbashi' => [
                [
                    'destination' => 'Kolwezi',
                    'distance' => '320.00', // Reçoit un string
                    'duration' => 300,
                    'code' => 'RT-LUB-001',
                    'stops' => ['Lubumbashi', 'Likasi', 'Kolwezi']
                ],
                [
                    'destination' => 'Likasi',
                    'distance' => '120.00', // Reçoit un string
                    'duration' => 120,
                    'code' => 'RT-LUB-002',
                    'stops' => ['Lubumbashi', 'Likasi']
                ],
            ],
            'kinshasa' => [
                [
                    'destination' => 'Matadi',
                    'distance' => '350.00', // Reçoit un string
                    'duration' => 360,
                    'code' => 'RT-KIN-001',
                    'stops' => ['Kinshasa', 'Mbanza-Ngungu', 'Matadi']
                ],
                [
                    'destination' => 'Kikwit',
                    'distance' => '530.00', // Reçoit un string
                    'duration' => 480,
                    'code' => 'RT-KIN-002',
                    'stops' => ['Kinshasa', 'Kenge', 'Kikwit']
                ],
            ],
            'kolwezi' => [
                [
                    'destination' => 'Lubumbashi',
                    'distance' => '320.00', // Reçoit un string
                    'duration' => 300,
                    'code' => 'RT-KOL-001',
                    'stops' => ['Kolwezi', 'Likasi', 'Lubumbashi']
                ],
            ],
        ];

        foreach ($routesData as $originKey => $routes) {
            $agency = $agencies[$originKey];

            foreach ($routes as $rData) {
                $route = (new Route())
                    ->setAgency($agency)
                    ->setName(sprintf('%s - %s', ucfirst($originKey), $rData['destination']))
                    ->setCode($rData['code'])
                    ->setSlug(strtolower(sprintf('%s-%s', $originKey, $rData['destination'])))
                    ->setDistance($rData['distance']) // Reçoit la chaîne
                    ->setDuration($rData['duration'])
                    ->setIsAvailable(true)
                    ->setReservationExpirationDelay(120)
                    ->setPricePerKg('600.00')
                    ->setCurrencyPricePerKg($cdf) // Reçoit l'objet Currency
                    ->setCreatedBy($admin); // Reçoit l'objet User
                $manager->persist($route);

                // Arrêts (MCD 15)
                foreach ($rData['stops'] as $index => $cityName) {
                    $stop = (new RouteStop())
                        ->setRoute($route)
                        ->setStopOrder($index + 1)
                        ->setCity($cityName)
                        ->setCountry('RDC')
                        ->setDistanceFromDeparture((string)(((float)$rData['distance'] / count($rData['stops'])) * $index))

                        ->setDurationFromDeparture((int)($rData['duration'] / count($rData['stops']) * $index))
                        ->setIsActive(true)
                        ->setCreatedBy($admin); // Reçoit l'objet User
                    $manager->persist($stop);
                }
                // Programmation des Voyages (MCD 16)
                $trip = (new Trip())
                    ->setRoute($route)
                    ->setBus($buses[$originKey])
                    ->setDepartureDate(new \DateTimeImmutable('+1 day'))
                    ->setDepartureTime(new \DateTimeImmutable('08:00:00'))
                    ->setArrivalDate(new \DateTimeImmutable('+1 day'))
                    ->setArrivalTime((new \DateTimeImmutable('08:00:00'))->modify(sprintf('+%d minutes', $rData['duration'])))
                    ->setDriver($drivers[$originKey][0])
                    ->setStatus(TripStatus::Scheduled) // Première lettre en Majuscule
                    ->setCode(sprintf('TRP-%s-%d', $rData['code'], rand(100, 999)))
                    ->setSlug(strtolower(sprintf('trip-%s', $rData['code'])))
                    ->setCreatedBy($admin); // Reçoit l'objet User
                $manager->persist($trip);
                // Mapping des TripSeats d'inventaire dynamique (MCD 17)
                $seats = $manager->getRepository(Seat::class)->findBy(['bus' => $buses[$originKey]]);
                foreach ($seats as $seat) {
                    $tripSeat = (new TripSeat())
                        ->setTrip($trip)
                        ->setSeat($seat)
                        ->setSeatStatus(TripSeatStatus::Available) // Première lettre en Majuscule
                        ->setReservation(null)
                        ->setHeldUntil(null);
                    $manager->persist($tripSeat);
                }
            }
        }
        $manager->flush();
    }
}
