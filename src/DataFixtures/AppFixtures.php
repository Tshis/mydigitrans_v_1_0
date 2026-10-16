<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Currency;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\BranchStatus;
use App\Enum\Scope;
use App\Enum\UserType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        /*
         * 1. ROLES
         */
        $rolesData = [
            'super_admin' => [
                'name' => 'Super administrateur',
                'code' => 'ROLE_SUPER_ADMIN',
                'scope' => Scope::Platform,
            ],
            'agency_admin' => [
                'name' => 'Administrateur agence',
                'code' => 'ROLE_AGENCY_ADMIN',
                'scope' => Scope::Agency,
            ],
            'cashier' => [
                'name' => 'Caissier',
                'code' => 'ROLE_CASHIER',
                'scope' => Scope::Agency,
            ],
            'partner' => [
                'name' => 'Partenaire',
                'code' => 'ROLE_PARTNER',
                'scope' => Scope::Platform,
            ],
            'client' => [
                'name' => 'Client',
                'code' => 'ROLE_CLIENT',
                'scope' => Scope::Platform,
            ],
        ];

        $roles = [];

        foreach ($rolesData as $key => $data) {
            $role = (new Role())
                ->setName($data['name'])
                ->setCode($data['code'])
                ->setScope($data['scope'])
                ->setIsActive(true);

            $manager->persist($role);
            $roles[$key] = $role;
        }

        $manager->flush();

        /*
         * 2. USERS
         *
         * Mot de passe commun de développement :
         * Password123!
         */
        $usersData = [
            'admin' => [
                'firstname' => 'Admin',
                'lastname' => 'Platform',
                'email' => 'admin@example.com',
                'phone' => '+243810000001',
                'type' => UserType::Platform,
                'code' => 'USR-0001',
                'role' => 'super_admin',
            ],
            'agency_admin_1' => [
                'firstname' => 'Jean',
                'lastname' => 'Mwamba',
                'email' => 'jean.mwamba@example.com',
                'phone' => '+243810000002',
                'type' => UserType::Agency,
                'code' => 'USR-0002',
                'role' => 'agency_admin',
            ],
            'cashier_1' => [
                'firstname' => 'Marie',
                'lastname' => 'Ilunga',
                'email' => 'marie.ilunga@example.com',
                'phone' => '+243810000003',
                'type' => UserType::Agency,
                'code' => 'USR-0003',
                'role' => 'cashier',
            ],
            'agency_admin_2' => [
                'firstname' => 'Patrick',
                'lastname' => 'Kasongo',
                'email' => 'patrick.kasongo@example.com',
                'phone' => '+243810000004',
                'type' => UserType::Agency,
                'code' => 'USR-0004',
                'role' => 'agency_admin',
            ],
            'partner' => [
                'firstname' => 'David',
                'lastname' => 'Banza',
                'email' => 'david.banza@example.com',
                'phone' => '+243810000005',
                'type' => UserType::Partner,
                'code' => 'USR-0005',
                'role' => 'partner',
            ],
            'client_1' => [
                'firstname' => 'Grace',
                'lastname' => 'Lukusa',
                'email' => 'grace.lukusa@example.com',
                'phone' => '+243810000006',
                'type' => UserType::Client,
                'code' => 'USR-0006',
                'role' => 'client',
            ],
            'client_2' => [
                'firstname' => 'Michel',
                'lastname' => 'Tshibanda',
                'email' => 'michel.tshibanda@example.com',
                'phone' => '+243810000007',
                'type' => UserType::Client,
                'code' => 'USR-0007',
                'role' => 'client',
            ],
        ];

        $users = [];

        foreach ($usersData as $key => $data) {
            $user = (new User())
                ->setFirstname($data['firstname'])
                ->setLastname($data['lastname'])
                ->setEmail($data['email'])
                ->setPhone($data['phone'])
                ->setUserType($data['type'])
                ->setCode($data['code'])
                ->setIsActive(true)
                ->setIsOnline(false)
                ->setIsLegalAccepted(true)
                ->setLegalAcceptedAt(new \DateTimeImmutable())
                ->setReferralToken(bin2hex(random_bytes(16)));

            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    'Password123!'
                )
            );

            $manager->persist($user);
            $users[$key] = $user;
        }

        // Parrainage : les deux clients sont parrainés par le partenaire.
        $users['client_1']->setParent($users['partner']);
        $users['client_2']->setParent($users['partner']);

        $manager->flush();

        /*
         * 3. CURRENCIES
         */
        $currenciesData = [
            'USD' => 'Dollar américain',
            'CDF' => 'Franc congolais',
            'EUR' => 'Euro',
        ];

        $currencies = [];

        foreach ($currenciesData as $code => $name) {
            $currency = (new Currency())
                ->setCode($code)
                ->setName($name)
                ->setCreatedBy($users['admin']);

            $manager->persist($currency);
            $currencies[$code] = $currency;
        }

        $manager->flush();

        /*
         * 4. AGENCIES
         */
        $agenciesData = [
            'lubumbashi' => [
                'name' => 'Katanga Travel',
                'email' => 'contact.katanga@example.com',
                'phone' => '+243820000001',
                'city' => 'Lubumbashi',
                'address' => '12 Avenue Lumumba',
                'code' => 'AG-0001',
                'slug' => 'katanga-travel',
                'currency' => 'USD',
            ],
            'kinshasa' => [
                'name' => 'Congo Express',
                'email' => 'contact.congoexpress@example.com',
                'phone' => '+243820000002',
                'city' => 'Kinshasa',
                'address' => '25 Boulevard du 30 Juin',
                'code' => 'AG-0002',
                'slug' => 'congo-express',
                'currency' => 'CDF',
            ],
            'kolwezi' => [
                'name' => 'Lualaba Services',
                'email' => 'contact.lualaba@example.com',
                'phone' => '+243820000003',
                'city' => 'Kolwezi',
                'address' => '8 Avenue de la République',
                'code' => 'AG-0003',
                'slug' => 'lualaba-services',
                'currency' => 'USD',
            ],
        ];

        $agencies = [];

        foreach ($agenciesData as $key => $data) {
            $agency = (new Agency())
                ->setName($data['name'])
                ->setEmail($data['email'])
                ->setPhone($data['phone'])
                ->setAddress($data['address'])
                ->setCountry('République démocratique du Congo')
                ->setCity($data['city'])
                ->setCode($data['code'])
                ->setSlug($data['slug'])
                ->setRegistrationNumber(
                    'RCCM-TEST-' . str_pad(
                        (string) (count($agencies) + 1),
                        4,
                        '0',
                        STR_PAD_LEFT
                    )
                )
                ->setTaxNumber(
                    'NIF-TEST-' . str_pad(
                        (string) (count($agencies) + 1),
                        4,
                        '0',
                        STR_PAD_LEFT
                    )
                )
                ->setDefaultCurrency($currencies[$data['currency']])
                ->setIsActive(true)
                ->setIsVerified(true)
                ->setUpdatedBy($users['admin'])
                ->setParent($users['partner']);

            $manager->persist($agency);
            $agencies[$key] = $agency;
        }

        $manager->flush();

        /*
         * 5. BRANCHES
         */
        $branchesData = [
            [
                'name' => 'Katanga Centre',
                'agency' => 'lubumbashi',
                'city' => 'Lubumbashi',
                'code' => 'BR-001',
                'slug' => 'katanga-centre',
                'status' => BranchStatus::Opened,
            ],
            [
                'name' => 'Katanga Annexe',
                'agency' => 'lubumbashi',
                'city' => 'Lubumbashi',
                'code' => 'BR-002',
                'slug' => 'katanga-annexe',
                'status' => BranchStatus::Opened,
            ],
            [
                'name' => 'Congo Gombe',
                'agency' => 'kinshasa',
                'city' => 'Kinshasa',
                'code' => 'BR-001',
                'slug' => 'congo-gombe',
                'status' => BranchStatus::Opened,
            ],
            [
                'name' => 'Lualaba Centre',
                'agency' => 'kolwezi',
                'city' => 'Kolwezi',
                'code' => 'BR-001',
                'slug' => 'lualaba-centre',
                'status' => BranchStatus::Suspended,
            ],
        ];

        $branches = [];

        foreach ($branchesData as $index => $data) {
            $branch = (new Branch())
                ->setName($data['name'])
                ->setAgency($agencies[$data['agency']])
                ->setCity($data['city'])
                ->setCountry('République démocratique du Congo')
                ->setCode($data['code'])
                ->setSlug($data['slug'])
                ->setStatus($data['status']);

            $manager->persist($branch);
            $branches[$index] = $branch;
        }

        $manager->flush();

        /*
         * 6. USER-AGENCY-BRANCH ASSOCIATIONS
         */
        $users['agency_admin_1']
            ->setAgency($agencies['lubumbashi'])
            ->setBranch($branches[0]);

        $users['cashier_1']
            ->setAgency($agencies['lubumbashi'])
            ->setBranch($branches[1]);

        $users['agency_admin_2']
            ->setAgency($agencies['kinshasa'])
            ->setBranch($branches[2]);

        $manager->flush();

        /*
         * 7. USER ROLES
         */
        $assignments = [
            [
                'user' => 'admin',
                'role' => 'super_admin',
                'agency' => null,
                'branch' => null,
            ],
            [
                'user' => 'agency_admin_1',
                'role' => 'agency_admin',
                'agency' => 'lubumbashi',
                'branch' => 0,
            ],
            [
                'user' => 'cashier_1',
                'role' => 'cashier',
                'agency' => 'lubumbashi',
                'branch' => 1,
            ],
            [
                'user' => 'agency_admin_2',
                'role' => 'agency_admin',
                'agency' => 'kinshasa',
                'branch' => 2,
            ],
            [
                'user' => 'partner',
                'role' => 'partner',
                'agency' => null,
                'branch' => null,
            ],
            [
                'user' => 'client_1',
                'role' => 'client',
                'agency' => null,
                'branch' => null,
            ],
            [
                'user' => 'client_2',
                'role' => 'client',
                'agency' => null,
                'branch' => null,
            ],
        ];

        foreach ($assignments as $assignment) {
            $userRole = (new UserRole())
                ->setUser($users[$assignment['user']])
                ->setRole($roles[$assignment['role']])
                ->setAssignedBy($users['admin']);

            if ($assignment['agency'] !== null) {
                $userRole->setAgency(
                    $agencies[$assignment['agency']]
                );
            }

            if ($assignment['branch'] !== null) {
                $userRole->setBranch(
                    $branches[$assignment['branch']]
                );
            }

            $manager->persist($userRole);
        }

        $manager->flush();
    }
}
