<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\UserType;
use App\Enum\BranchStatus;
use App\Service\SystemActor; // <-- Signature robot système
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AgencyTestDataFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SystemActor $systemActor // Injection du service robot pour assumer la traçabilité racine
    ) {}

    public function load(ObjectManager $manager): void
    {
        // 1. Récupération de l'acteur robot système pour les attributions d'admins principaux
        $robot = $this->systemActor->get();

        // Récupération des rôles d'usine créés au préalable par le Seed
        $roleAdminDb = $manager->getRepository(Role::class)->findOneBy(['code' => 'ROLE_AGENCY_ADMIN']);
        $roleManagerDb = $manager->getRepository(Role::class)->findOneBy(['code' => 'ROLE_MANAGER']);
        $roleCashierDb = $manager->getRepository(Role::class)->findOneBy(['code' => 'ROLE_CASHIER']);
        $roleDriverDb = $manager->getRepository(Role::class)->findOneBy(['code' => 'ROLE_DRIVER']);

        if (!$roleAdminDb || !$roleManagerDb || !$roleCashierDb || !$roleDriverDb) {
            throw new \RuntimeException('Les rôles d\'usine sont introuvables. Lancez d\'abord la commande app:access:seed !');
        }

        // Configuration des 3 compagnies à insérer
        $agenciesData = [
            [
                'name' => 'Trans Express Congo',
                'code' => 'TEC-RDC',
                'slug' => 'trans-express-congo',
                'city' => 'Kinshasa',
                'branch_name' => 'Gare Centrale - Kinshasa',
                'branch_code' => 'KIN-CEN',
                'branch_slug' => 'gare-centrale-kinshasa',
                'prefix' => 'TEC'
            ],
            [
                'name' => 'Congo Highway Transport',
                'code' => 'CHT-RDC',
                'slug' => 'congo-highway-transport',
                'city' => 'Goma',
                'branch_name' => 'Gare du Lac - Goma',
                'branch_code' => 'GOM-LAC',
                'branch_slug' => 'gare-du-lac-goma',
                'prefix' => 'CHT'
            ],
            [
                'name' => 'Fleuve Majesté Logistique',
                'code' => 'FML-RDC',
                'slug' => 'fleuve-majeste-logistique',
                'city' => 'Matadi',
                'branch_name' => 'Agence Portuaire - Matadi',
                'branch_code' => 'MAT-POR',
                'branch_slug' => 'agence-portuaire-matadi',
                'prefix' => 'FML'
            ]
        ];

        foreach ($agenciesData as $index => $data) {
            // Protection contre la duplication si --append est relancé
            $existing = $manager->getRepository(Agency::class)->findOneBy(['code' => $data['code']]);
            if ($existing) {
                continue;
            }

            // =========================================================================
            // A. CRÉATION DE L'AGENCE (MCD Table 21)
            // =========================================================================
            $agency = new Agency();
            $agency->setName($data['name'])
                ->setCode($data['code'])
                ->setSlug($data['slug'])
                ->setPhone('+24381000000' . $index)
                ->setEmail('contact@' . $data['slug'] . '.cd')
                ->setAddress('Numéro ' . (12 + $index) . ', Avenue de l\'Uranium, ' . $data['city'])
                ->setCity($data['city'])
                ->setCountry('République Démocratique du Congo')
                ->setIsActive(true)
                ->setIsVerified(true);
            $manager->persist($agency);

            // =========================================================================
            // B. CRÉATION DE LA SOUCCURSALE (BRANCH - MCD Table 19)
            // =========================================================================
            $branch = new Branch();
            $branch->setName($data['branch_name'])
                ->setCode($data['branch_code'])
                ->setSlug($data['branch_slug'])
                ->setCity($data['city'])
                ->setCountry('République Démocratique du Congo')
                ->setStatus(BranchStatus::Opened)
                ->setAgency($agency);
            $manager->persist($branch);

            // =========================================================================
            // C. CRÉATION DES 4 COLLABORATEURS OBLIGATOIRES PAR COMPAGNIE (MCD Table 20)
            // =========================================================================
            $p = $data['prefix'];

            // 1. L'Administrateur Principal (Propriétaire racine)
            $owner = new User();
            $owner->setFirstname('Admin')
                ->setLastname($p)
                ->setEmail('admin@' . $data['slug'] . '.cd')
                ->setCode('AGN-' . $p . '-001')
                ->setPhone('+24382000010' . $index)
                ->setUserType(UserType::Agency)
                ->setAgency($agency)
                ->setBranch(null)
                ->setParent(null)
                ->setIsActive(true)
                ->setIsOnline(false);
            $owner->setPassword($this->passwordHasher->hashPassword($owner, 'Password1234'));
            $manager->persist($owner);

            // Liaison physique UserRole signée par le Robot Système
            $urOwner = new UserRole();
            $urOwner->setUser($owner)
                ->setRole($roleAdminDb)
                ->setAgency($agency)
                ->setBranch(null)
                ->setAssignedBy($robot); // 🟢 FIX : Plus de NotNullViolation pour assigned_by_id
            $manager->persist($urOwner);

            // 2. Le Gestionnaire / Manager de la branche locale
            $managerGare = new User();
            $managerGare->setFirstname('Manager')
                ->setLastname($p)
                ->setEmail('manager@' . $data['slug'] . '.cd')
                ->setCode('AGN-' . $p . '-002')
                ->setPhone('+24382000020' . $index)
                ->setUserType(UserType::Agency)
                ->setAgency($agency)
                ->setBranch($branch)
                ->setParent($owner)
                ->setIsActive(true)
                ->setIsOnline(false);
            $managerGare->setPassword($this->passwordHasher->hashPassword($managerGare, 'Password1234'));
            $manager->persist($managerGare);

            // Liaison physique UserRole signée par le Propriétaire (Owner)
            $urManager = new UserRole();
            $urManager->setUser($managerGare)
                ->setRole($roleManagerDb)
                ->setAgency($agency)
                ->setBranch($branch)
                ->setAssignedBy($owner); // 🟢 FIX : Plus de NotNullViolation pour assigned_by_id
            $manager->persist($urManager);

            // 3. Le Caissier / Guichetier
            $cashier = new User();
            $cashier->setFirstname('Guichetier')
                ->setLastname($p)
                ->setEmail('caissier@' . $data['slug'] . '.cd')
                ->setCode('AGN-' . $p . '-003')
                ->setPhone('+24382000030' . $index)
                ->setUserType(UserType::Agency)
                ->setAgency($agency)
                ->setBranch($branch)
                ->setParent($owner)
                ->setIsActive(true)
                ->setIsOnline(false);
            $cashier->setPassword($this->passwordHasher->hashPassword($cashier, 'Password1234'));
            $manager->persist($cashier);

            $urCashier = new UserRole();
            $urCashier->setUser($cashier)
                ->setRole($roleCashierDb)
                ->setAgency($agency)
                ->setBranch($branch)
                ->setAssignedBy($owner); // 🟢 FIX : Plus de NotNullViolation pour assigned_by_id
            $manager->persist($urCashier);

            // 4. Le Chauffeur / Driver
            $driver = new User();
            $driver->setFirstname('Chauffeur')
                ->setLastname($p)
                ->setEmail('chauffeur@' . $data['slug'] . '.cd')
                ->setCode('AGN-' . $p . '-004')
                ->setPhone('+24382000040' . $index)
                ->setUserType(UserType::Agency)
                ->setAgency($agency)
                ->setBranch($branch)
                ->setParent($owner)
                ->setIsActive(true)
                ->setIsOnline(false);
            $driver->setPassword($this->passwordHasher->hashPassword($driver, 'Password1234'));
            $manager->persist($driver);

            $urDriver = new UserRole();
            $urDriver->setUser($driver)
                ->setRole($roleDriverDb)
                ->setAgency($agency)
                ->setBranch($branch)
                ->setAssignedBy($owner); // 🟢 FIX : Plus de NotNullViolation pour assigned_by_id
            $manager->persist($urDriver);
        }

        $manager->flush();
    }
}
