<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Agency;
use App\Entity\Bus;
use App\Entity\BusLayout;
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
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TripDataFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /*
         * 1. RÉCUPÉRER LES DONNÉES DE BASE
         */
        $admin = $manager->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        if (!$admin instanceof User) {
            throw new \RuntimeException(
                'Utilisateur admin@example.com introuvable. '
                    . 'Exécute AppFixtures avant TripDataFixtures.'
            );
        }

        $agencyAdmins = [
            'lubumbashi' => $manager->getRepository(User::class)
                ->findOneBy(['email' => 'jean.mwamba@example.com']),
            'kinshasa' => $manager->getRepository(User::class)
                ->findOneBy(['email' => 'patrick.kasongo@example.com']),
            'kolwezi' => $admin,
        ];

        $agencies = [
            'lubumbashi' => $manager->getRepository(Agency::class)
                ->findOneBy(['code' => 'AG-0001']),
            'kinshasa' => $manager->getRepository(Agency::class)
                ->findOneBy(['code' => 'AG-0002']),
            'kolwezi' => $manager->getRepository(Agency::class)
                ->findOneBy(['code' => 'AG-0003']),
        ];

        foreach ($agencies as $key => $agency) {
            if (!$agency instanceof Agency) {
                throw new \RuntimeException(
                    sprintf('Agence "%s" introuvable.', $key)
                );
            }
        }

        /*
         * 2. CRÉER LE RÔLE DRIVER
         */
        $driverRole = $manager->getRepository(Role::class)
            ->findOneBy(['code' => 'ROLE_DRIVER']);

        if (!$driverRole instanceof Role) {
            $driverRole = (new Role())
                ->setName('Conducteur')
                ->setCode('ROLE_DRIVER')
                ->setScope(Scope::Agency)
                ->setDescription('Conducteur de bus')
                ->setIsActive(true);

            $manager->persist($driverRole);
            $manager->flush();
        }

        /*
         * 3. CRÉER LES CONDUCTEURS
         */
        $drivers = [];

        foreach ($agencies as $agencyKey => $agency) {
            $drivers[$agencyKey] = [];

            for ($i = 1; $i <= 2; ++$i) {
                $email = sprintf(
                    'driver.%s.%d@example.com',
                    $agencyKey,
                    $i
                );

                $driver = $manager->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if (!$driver instanceof User) {
                    $driver = (new User())
                        ->setFirstname(
                            $i === 1 ? 'Jean' : 'Paul'
                        )
                        ->setLastname(
                            sprintf('Conducteur %s %d', $agencyKey, $i)
                        )
                        ->setEmail($email)
                        ->setPhone(sprintf(
                            '+243830%06d',
                            count($drivers[$agencyKey]) + 1
                                + array_search(
                                    $agencyKey,
                                    array_keys($agencies),
                                    true
                                ) * 10
                        ))
                        ->setUserType(UserType::Agency)
                        ->setCode(sprintf(
                            'DRV-%s-%02d',
                            strtoupper(substr($agencyKey, 0, 3)),
                            $i
                        ))
                        ->setAgency($agency)
                        ->setIsActive(true)
                        ->setIsOnline(false)
                        ->setPassword(
                            $this->passwordHasher->hashPassword(
                                $driver = new User(),
                                'Password123!'
                            )
                        );

                    // Reconstruire l'utilisateur avec le mot de passe hashé
                    // pour que le hash corresponde bien à cet utilisateur.
                    $driver = (new User())
                        ->setFirstname(
                            $i === 1 ? 'Jean' : 'Paul'
                        )
                        ->setLastname(
                            sprintf('Conducteur %s %d', $agencyKey, $i)
                        )
                        ->setEmail($email)
                        ->setPhone(sprintf(
                            '+243830%06d',
                            1 + $i
                                + array_search(
                                    $agencyKey,
                                    array_keys($agencies),
                                    true
                                ) * 10
                        ))
                        ->setUserType(UserType::Agency)
                        ->setCode(sprintf(
                            'DRV-%s-%02d',
                            strtoupper(substr($agencyKey, 0, 3)),
                            $i
                        ))
                        ->setAgency($agency)
                        ->setIsActive(true)
                        ->setIsOnline(false);

                    $driver->setPassword(
                        $this->passwordHasher->hashPassword(
                            $driver,
                            'Password123!'
                        )
                    );

                    $manager->persist($driver);

                    $userRole = (new UserRole())
                        ->setUser($driver)
                        ->setRole($driverRole)
                        ->setAgency($agency)
                        ->setAssignedBy($admin);

                    $manager->persist($userRole);
                }

                $drivers[$agencyKey][] = $driver;
            }
        }

        $manager->flush();

        /*
         * 4. CONFIGURATION DES ROUTES
         */
        $routesData = [
            'lubumbashi' => [
                [
                    'destination' => 'Kolwezi',
                    'distance' => '320.00',
                    'duration' => 300,
                    'code' => 'RT-LUB-001',
                ],
                [
                    'destination' => 'Likasi',
                    'distance' => '120.00',
                    'duration' => 120,
                    'code' => 'RT-LUB-002',
                ],
            ],
            'kinshasa' => [
                [
                    'destination' => 'Matadi',
                    'distance' => '350.00',
                    'duration' => 360,
                    'code' => 'RT-KIN-001',
                ],
                [
                    'destination' => 'Kikwit',
                    'distance' => '530.00',
                    'duration' => 480,
                    'code' => 'RT-KIN-002',
                ],
            ],
            'kolwezi' => [
                [
                    'destination' => 'Lubumbashi',
                    'distance' => '320.00',
                    'duration' => 300,
                    'code' => 'RT-KOL-001',
                ],
                [
                    'destination' => 'Likasi',
                    'distance' => '200.00',
                    'duration' => 210,
                    'code' => 'RT-KOL-002',
                ],
            ],
        ];

        /*
         * 5. CRÉER LES DISPOSITIONS, BUS ET SIÈGES
         */
        $buses = [];

        foreach ($agencies as $agencyKey => $agency) {
            $layout = (new BusLayout())
                ->setName('Disposition standard 40 places')
                ->setAgency($agency)
                ->setRows(10)
                ->setColumns(4)
                ->setAisles([])
                ->setSpecialPositions([])
                ->setHasBackExtraSeat(false)
                ->setIsValidated(true)
                ->setCode(sprintf(
                    'LAY-%s-001',
                    strtoupper(substr($agencyKey, 0, 3))
                ));

            $manager->persist($layout);

            $bus = (new Bus())
                ->setAgency($agency)
                ->setBusLayout($layout)
                ->setCode(sprintf(
                    'BUS-%s-001',
                    strtoupper(substr($agencyKey, 0, 3))
                ))
                ->setName('Autocar principal')
                ->setPlateNumber(sprintf(
                    'TEST-%03d',
                    array_search(
                        $agencyKey,
                        array_keys($agencies),
                        true
                    ) + 1
                ))
                ->setBrand('Mercedes-Benz')
                ->setModel('Tourismo')
                ->setStatus(BusStatus::Active)
                ->setMileage(85000)
                ->setYear(2022)
                ->setCreatedBy($admin);

            $manager->persist($bus);
            $buses[$agencyKey] = $bus;

            for ($row = 1; $row <= 10; ++$row) {
                for ($column = 1; $column <= 4; ++$column) {
                    $label = chr(64 + $row) . $column;

                    $seat = (new Seat())
                        ->setBus($bus)
                        ->setSeatLabel($label)
                        ->setType(
                            $row === 1
                                ? SeatType::Vip
                                : SeatType::Normal
                        )
                        ->setRow($row)
                        ->setColumn($column);

                    $manager->persist($seat);
                }
            }
        }

        $manager->flush();

        /*
         * 6. CRÉER LES ROUTES ET LEURS ARRÊTS
         */
        $routes = [];

        foreach ($routesData as $agencyKey => $agencyRoutes) {
            $agency = $agencies[$agencyKey];
            $creator = $agencyAdmins[$agencyKey];

            foreach ($agencyRoutes as $index => $data) {
                $origin = $agency->getCity();

                $route = (new Route())
                    ->setAgency($agency)
                    ->setName($origin . ' - ' . $data['destination'])
                    ->setCode($data['code'])
                    ->setSlug(strtolower($data['code']))
                    ->setDistance($data['distance'])
                    ->setDuration($data['duration'])
                    ->setCreatedBy($creator)
                    ->setIsAvailable(true)
                    ->setReservationExpirationDelay(15)
                    ->setPricePerKg('2.50')
                    ->setCurrencyPricePerKg(
                        $agency->getDefaultCurrency()
                    );

                $manager->persist($route);
                $routes[$agencyKey][] = $route;

                $stop = (new RouteStop())
                    ->setRoute($route)
                    ->setStopOrder(1)
                    ->setCity($data['destination'])
                    ->setCountry('République démocratique du Congo')
                    ->setDistanceFromDeparture($data['distance'])
                    ->setDurationFromDeparture($data['duration'])
                    ->setIsActive(true)
                    ->setCreatedBy($creator);

                $manager->persist($stop);
            }
        }

        $manager->flush();

        /*
         * 7. CRÉER LES VOYAGES
         */
        $tripNumber = 1;
        $baseDate = new \DateTimeImmutable('today');

        foreach ($routes as $agencyKey => $agencyRoutes) {
            foreach ($agencyRoutes as $routeIndex => $route) {
                for ($occurrence = 1; $occurrence <= 2; ++$occurrence) {
                    $departure = $baseDate
                        ->modify(sprintf(
                            '+%d days',
                            $tripNumber
                        ))
                        ->setTime(8 + ($occurrence * 2), 0);

                    $arrival = $departure->modify(sprintf(
                        '+%d minutes',
                        $route->getDuration()
                    ));

                    $driverIndex = ($occurrence - 1) % 2;
                    $driver = $drivers[$agencyKey][$driverIndex];
                    $coDriver = $drivers[$agencyKey][1 - $driverIndex];

                    $trip = (new Trip())
                        ->setRoute($route)
                        ->setBus($buses[$agencyKey])
                        ->setDepartureDate(
                            $departure->setTime(0, 0)
                        )
                        ->setDepartureTime(
                            new \DateTimeImmutable(
                                $departure->format('H:i:s')
                            )
                        )
                        ->setArrivalDate(
                            $arrival->setTime(0, 0)
                        )
                        ->setArrivalTime(
                            new \DateTimeImmutable(
                                $arrival->format('H:i:s')
                            )
                        )
                        ->setDriver($driver)
                        ->setCoDriver($coDriver)
                        ->setStatus(TripStatus::Scheduled)
                        ->setCode(sprintf('TRIP-%04d', $tripNumber))
                        ->setSlug(sprintf('trip-%04d', $tripNumber))
                        ->setCreatedBy($agencyAdmins[$agencyKey]);

                    $manager->persist($trip);
                    $manager->flush();

                    /*
                     * 8. ASSOCIER LES SIÈGES AU VOYAGE
                     */
                    $seats = $manager->getRepository(Seat::class)
                        ->findBy(
                            ['bus' => $buses[$agencyKey]],
                            ['row' => 'ASC', 'column' => 'ASC']
                        );

                    foreach ($seats as $index => $seat) {
                        $tripSeat = (new TripSeat())
                            ->setTrip($trip)
                            ->setSeat($seat);

                        if (($index + 1) % 19 === 0) {
                            $tripSeat->setSeatStatus(
                                TripSeatStatus::Blocked
                            );
                        } elseif (($index + 1) % 13 === 0) {
                            $tripSeat
                                ->setSeatStatus(TripSeatStatus::Held)
                                ->setHeldUntil(
                                    new \DateTimeImmutable('+15 minutes')
                                );
                        } else {
                            $tripSeat->setSeatStatus(
                                TripSeatStatus::Available
                            );
                        }

                        $manager->persist($tripSeat);
                    }

                    ++$tripNumber;
                }
            }
        }

        $manager->flush();
    }
}
