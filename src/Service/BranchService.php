<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\BranchData;
use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\BranchStatus;
use App\Enum\Scope;
use App\Security\AccessCatalog;
use App\Security\PermissionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Service de gestion des succursales, de leur personnel et de la nomination du gérant.
 */
final class BranchService
{
    public const BRANCH_MANAGER_ROLE_CODE = 'ROLE_MANAGER';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Hydrate un DTO BranchData à partir d'une succursale existante pour l'édition.
     */
    public function createDtoFromBranch(Branch $branch): BranchData
    {
        $dto = new BranchData();
        $dto->name    = $branch->getName();
        $dto->code    = $branch->getCode();
        $dto->city    = $branch->getCity();
        $dto->country = $branch->getCountry();

        $manager = $this->findActiveBranchManager($branch);
        if ($manager instanceof User) {
            $dto->managerId = $manager->getId();
        }

        return $dto;
    }

    /**
     * Retourne les agents actifs de l'agence éligibles au poste de gérant.
     *
     * @return list<User>
     */
    public function getAgencyAgents(Agency $agency): array
    {
        return $this->em->getRepository(User::class)->findBy(
            ['agency' => $agency, 'isActive' => true],
            ['lastname' => 'ASC', 'firstname' => 'ASC']
        );
    }

    /**
     * Liste toutes les succursales de l'agence et calcule les statistiques globales.
     *
     * @return array{
     *     branches: list<array<string, mixed>>,
     *     stats: array{total: int, active: int, suspended: int}
     * }
     */
    public function listAgencyBranches(Agency $agency): array
    {
        /** @var list<Branch> $rawBranches */
        $rawBranches = $this->em->getRepository(Branch::class)->findBy(
            ['agency' => $agency],
            ['createdAt' => 'DESC']
        );

        $stats = [
            'total'     => \count($rawBranches),
            'active'    => 0,
            'suspended' => 0,
        ];

        $branches = [];
        foreach ($rawBranches as $branch) {
            $isOpened = BranchStatus::Opened === $branch->getStatus();
            if ($isOpened) {
                ++$stats['active'];
            } else {
                ++$stats['suspended'];
            }

            $staff = $this->em->getRepository(User::class)->findBy(['branch' => $branch], ['id' => 'ASC']);
            $manager = $this->findActiveBranchManager($branch);
            $managerName = $manager instanceof User
                ? ($manager->getFullName() ?: $manager->getEmail())
                : 'Non assigné';

            $countryCode = (string) $branch->getCountry();
            $countryName = (2 === \strlen($countryCode) && Countries::exists($countryCode))
                ? Countries::getName($countryCode, 'fr')
                : $countryCode;

            $branches[] = [
                'id'          => $branch->getId(),
                'code'        => (string) $branch->getCode(),
                'slug'        => (string) $branch->getSlug(),
                'name'        => (string) $branch->getName(),
                'city'        => (string) $branch->getCity(),
                'country'     => $countryName,
                'managerName' => $managerName,
                'staffCount'  => \count($staff),
                'status'      => $isOpened ? 'active' : 'inactive',
                'statusLabel' => $isOpened ? 'Active' : 'Suspendue',
            ];
        }

        return [
            'branches' => $branches,
            'stats'    => $stats,
        ];
    }

    public function findAgencyBranchByCode(Agency $agency, string $code): ?Branch
    {
        return $this->em->getRepository(Branch::class)->findOneBy([
            'agency' => $agency,
            'code'   => $code,
        ]);
    }

    /**
     * Crée une nouvelle succursale et promeut automatiquement le gérant sélectionné en ROLE_MANAGER.
     *
     * @throws \DomainException
     */
    public function createBranch(User $actor, Agency $agency, BranchData $data): Branch
    {
        $name    = trim((string) $data->name);
        $city    = trim((string) $data->city);
        $country = strtoupper(trim((string) $data->country));

        $rawCode = strtoupper(trim((string) $data->code));
        $code    = '' !== $rawCode ? $rawCode : $this->generateUniqueBranchCode($agency, $city);

        if (null !== $this->findAgencyBranchByCode($agency, $code)) {
            throw new \DomainException(sprintf('Le code succursale [%s] est déjà utilisé dans votre agence.', $code));
        }

        $branch = new Branch();
        $branch->setAgency($agency)
            ->setName($name)
            ->setCode($code)
            ->setSlug($this->generateUniqueSlug($agency, $name))
            ->setCity($city)
            ->setCountry($country)
            ->setStatus(BranchStatus::Opened);

        $this->em->persist($branch);

        if (null !== $data->managerId) {
            $manager = $this->em->getRepository(User::class)->findOneBy([
                'id'     => $data->managerId,
                'agency' => $agency,
            ]);
            if ($manager instanceof User) {
                $this->promoteUserToBranchManager($branch, $manager, $actor);
            }
        }

        $this->em->flush();

        return $branch;
    }
    /**
     * Met à jour les informations d'une succursale et gère le changement éventuel de gérant.
     *
     * @throws \DomainException
     */
    public function updateBranch(User $actor, Branch $branch, BranchData $data): void
    {
        $agency  = $branch->getAgency() ?? throw new \LogicException('Agence introuvable.');
        $name    = trim((string) $data->name);
        $city    = trim((string) $data->city);
        $country = strtoupper(trim((string) $data->country));
        $rawCode = strtoupper(trim((string) $data->code));

        if ('' !== $rawCode && $rawCode !== $branch->getCode()) {
            $existing = $this->findAgencyBranchByCode($agency, $rawCode);
            if ($existing instanceof Branch && $existing->getId() !== $branch->getId()) {
                throw new \DomainException(sprintf('Le code succursale [%s] est déjà attribué à un autre point de vente.', $rawCode));
            }
            $branch->setCode($rawCode);
        }

        $branch->setName($name)
            ->setCity($city)
            ->setCountry($country);

        $currentManager = $this->findActiveBranchManager($branch);

        if (null === $data->managerId) {
            if ($currentManager instanceof User) {
                $this->revokeManagerRoleOnBranch($branch, $currentManager, $actor);
            }
        } elseif ($currentManager?->getId() !== $data->managerId) {
            $newManager = $this->em->getRepository(User::class)->findOneBy([
                'id'     => $data->managerId,
                'agency' => $agency,
            ]);
            if ($newManager instanceof User) {
                $this->promoteUserToBranchManager($branch, $newManager, $actor);
            }
        }

        $this->em->flush();
    }

    /**
     * Rassemble les données de la fiche technique d'une succursale et son personnel affecté.
     *
     * @return array<string, mixed>
     */
    public function getBranchProfile(Branch $branch): array
    {
        $isOpened = BranchStatus::Opened === $branch->getStatus();

        $countryCode = (string) $branch->getCountry();
        $countryName = (2 === \strlen($countryCode) && Countries::exists($countryCode))
            ? Countries::getName($countryCode, 'fr')
            : $countryCode;

        /** @var list<User> $users */
        $users = $this->em->getRepository(User::class)->findBy(
            ['branch' => $branch],
            ['lastname' => 'ASC']
        );

        $staffData = [];
        foreach ($users as $user) {
            $roleNames = [];
            foreach ($user->getUserRoles() as $userRole) {
                $role = $userRole->getRole();
                if (null === $userRole->getRevokedAt() && null !== $role && $role->isActive()) {
                    $roleNames[] = $role->getName();
                }
            }

            $staffData[] = [
                'name'      => $user->getFullName() ?: $user->getEmail(),
                'roleLabel' => [] !== $roleNames ? implode(', ', $roleNames) : 'Agent de guichet',
                'email'     => $user->getEmail(),
                'phone'     => $user->getPhone() ?? '-',
                'isActive'  => $user->isActive(),
            ];
        }

        return [
            'branch' => [
                'id'          => $branch->getId(),
                'code'        => $branch->getCode(),
                'slug'        => $branch->getSlug(),
                'name'        => $branch->getName(),
                'city'        => $branch->getCity(),
                'country'     => $countryName,
                'status'      => $isOpened ? 'active' : 'inactive',
                'statusLabel' => $isOpened ? 'Active' : 'Suspendue',
                'createdAt'   => $branch->getCreatedAt(),
                'updatedAt'   => $branch->getUpdatedAt(),
                'staff'       => $staffData,
            ],
        ];
    }

    /**
     * Bascule le statut opérationnel d'une succursale (active <-> inactive).
     */
    public function updateStatus(Branch $branch, string $targetState): void
    {
        if ('active' === $targetState) {
            $newStatus = BranchStatus::Opened;
        } else {
            $closedStatus = BranchStatus::tryFrom('suspended') ?? BranchStatus::tryFrom('closed');
            if (!$closedStatus instanceof BranchStatus) {
                foreach (BranchStatus::cases() as $case) {
                    if (BranchStatus::Opened !== $case) {
                        $closedStatus = $case;
                        break;
                    }
                }
            }
            $newStatus = $closedStatus ?? BranchStatus::Opened;
        }

        $branch->setStatus($newStatus);
        $this->em->flush();
    }

    /**
     * Promeut un utilisateur au rôle ROLE_MANAGER sur cette succursale et révoque l'éventuel ancien gérant.
     */
    private function promoteUserToBranchManager(Branch $branch, User $newManager, User $actor): void
    {
        $agency = $branch->getAgency() ?? throw new \LogicException('Agence introuvable.');
        $now = new \DateTimeImmutable();

        $managerRole = $this->em->getRepository(Role::class)->findOneBy([
            'code'  => self::BRANCH_MANAGER_ROLE_CODE,
            'scope' => Scope::Agency,
        ]);

        if (!$managerRole instanceof Role) {
            throw new \DomainException('Le rôle système ROLE_MANAGER est introuvable. Exécutez php bin/console app:access:seed.');
        }

        $previousManager = $this->findActiveBranchManager($branch);
        if ($previousManager instanceof User && $previousManager->getId() !== $newManager->getId()) {
            $this->revokeManagerRoleOnBranch($branch, $previousManager, $actor);
        }

        $newManager->setBranch($branch);

        $existingRoles = $newManager->getRoles();
        $isTopAdmin = \in_array(AccessCatalog::AGENCY_ADMIN, $existingRoles, true)
            || \in_array(AccessCatalog::GENERAL_MANAGER, $existingRoles, true);

        if (!$isTopAdmin) {
            foreach ($newManager->getUserRoles() as $activeUr) {
                if (null === $activeUr->getRevokedAt()) {
                    if ($activeUr->getRole()?->getCode() === self::BRANCH_MANAGER_ROLE_CODE && $activeUr->getBranch()?->getId() === $branch->getId()) {
                        $this->permissionResolver->resetCacheForUser($newManager);
                        return;
                    }
                    $activeUr->setRevokedAt($now)
                        ->setRevokedBy($actor);
                }
            }

            $userRole = new UserRole();
            $userRole->setUser($newManager)
                ->setRole($managerRole)
                ->setAgency($agency)
                ->setBranch($branch)
                ->setAssignedBy($actor);

            $this->em->persist($userRole);
        }

        $this->permissionResolver->resetCacheForUser($newManager);
    }

    private function revokeManagerRoleOnBranch(Branch $branch, User $manager, User $actor): void
    {
        $now = new \DateTimeImmutable();
        foreach ($manager->getUserRoles() as $ur) {
            if (
                null === $ur->getRevokedAt()
                && $ur->getRole()?->getCode() === self::BRANCH_MANAGER_ROLE_CODE
                && $ur->getBranch()?->getId() === $branch->getId()
            ) {
                $ur->setRevokedAt($now)
                    ->setRevokedBy($actor);
            }
        }
        $this->permissionResolver->resetCacheForUser($manager);
    }

    private function findActiveBranchManager(Branch $branch): ?User
    {
        if (null === $branch->getId()) {
            return null;
        }

        $ur = $this->em->createQueryBuilder()
            ->select('ur', 'u')
            ->from(UserRole::class, 'ur')
            ->join('ur.user', 'u')
            ->join('ur.role', 'r')
            ->andWhere('ur.branch = :branch')
            ->andWhere('ur.revokedAt IS NULL')
            ->andWhere('r.code = :roleCode')
            ->setParameter('branch', $branch)
            ->setParameter('roleCode', self::BRANCH_MANAGER_ROLE_CODE)
            ->orderBy('ur.assignedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $ur instanceof UserRole ? $ur->getUser() : null;
    }

    private function generateUniqueBranchCode(Agency $agency, string $city): string
    {
        $slugger = new AsciiSlugger('fr');
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $slugger->slug($city)->toString()) ?: 'SUC', 0, 3));
        $prefix = str_pad($prefix, 3, 'X');

        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Branch::class, 'b')
            ->andWhere('b.agency = :agency')
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getSingleScalarResult();

        for ($i = $count + 1; $i < $count + 500; ++$i) {
            $candidate = sprintf('SUC-%s-%02d', $prefix, $i);
            if (null === $this->findAgencyBranchByCode($agency, $candidate)) {
                return $candidate;
            }
        }

        return 'SUC-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function generateUniqueSlug(Agency $agency, string $name): string
    {
        $slugger = new AsciiSlugger('fr');
        $baseSlug = strtolower($slugger->slug($name)->toString());
        if ('' === $baseSlug) {
            $baseSlug = 'succursale';
        }

        $repo = $this->em->getRepository(Branch::class);
        $slug = $baseSlug;
        $counter = 2;

        while (null !== $repo->findOneBy(['agency' => $agency, 'slug' => $slug])) {
            $slug = $baseSlug . '-' . $counter;
            ++$counter;
        }

        return $slug;
    }
}
