<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\AgentData;
use App\Entity\Branch;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\BranchStatus;
use App\Enum\Scope;
use App\Enum\UserType;
use App\Exception\AgentManagementException;
use App\Security\PermissionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service de gestion des agents - Partie 1 : Déclaration et Dépendances.
 */
final class UserManagementService
{
    private const LIST_LIMIT = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PermissionResolver $permissions,
    ) {}

    public static function scopeOf(User $actor): Scope
    {
        return PermissionResolver::scopeOf($actor) ?? throw new \LogicException('Espace d\'administration introuvable.');
    }

    public static function actorBranch(User $actor): ?Branch
    {
        if (UserType::Agency !== $actor->getUserType()) {
            return null;
        }
        $branch = $actor->getBranch();
        return null === $branch || AgencyRegistrationService::HEADQUARTERS_CODE === $branch->getCode() ? null : $branch;
    }

    // ------------------------------------------------------------------ BLOC 2 : LECTURE & LISTING

    public function listAgents(User $actor, array $filters): array
    {
        $qb = $this->perimeter($actor)->select('u', 'b')->leftJoin('u.branch', 'b');

        $term = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        if ('' !== $term) {
            $qb->andWhere("(LOWER(u.firstname) LIKE :search OR LOWER(u.lastname) LIKE :search OR LOWER(u.email) LIKE :search OR u.phone LIKE :search OR LOWER(CONCAT(u.firstname, ' ', u.lastname)) LIKE :search)")
                ->setParameter('search', '%' . addcslashes($term, '%_\\') . '%');
        }

        if (null === self::actorBranch($actor) && !empty($filters['branch'])) {
            $qb->andWhere('b.code = :branchCode')->setParameter('branchCode', $filters['branch']);
        }

        if (\in_array($filters['status'] ?? null, ['active', 'inactive'], true)) {
            $qb->andWhere('u.isActive = :active')->setParameter('active', 'active' === $filters['status']);
        }

        /** @var list<User> $users */
        $users = $qb->orderBy('u.lastname', 'ASC')->addOrderBy('u.firstname', 'ASC')->setMaxResults(self::LIST_LIMIT)->getQuery()->getResult();
        $assignments = $this->currentAssignments($users);

        $rows = [];
        foreach ($users as $user) {
            $assignment = $assignments[(int) $user->getId()] ?? null;
            $role = $assignment?->getRole();

            $rows[] = [
                'firstname' => (string) $user->getFirstname(),
                'lastname' => (string) $user->getLastname(),
                'phone' => (string) $user->getPhone(),
                'email' => (string) $user->getEmail(),
                'isActive' => $user->isActive(),
                'isOnline' => $user->isOnline(),
                'lastLoginAt' => $user->getLastLoginAt(),
                'branchName' => $user->getBranch()?->getName(),
                'branchCode' => $user->getBranch()?->getCode(),
                'code' => (string) $user->getCode(),
                'role' => [
                    'name' => $role?->getName() ?? 'Aucune fonction',
                    'code' => (string) $role?->getCode(),
                    'description' => (string) $role?->getDescription(),
                    'isActive' => $role?->isActive() ?? false,
                ],
                'isSelf' => $user->getId() === $actor->getId(),
            ];
        }

        return $rows;
    }

    public function branchOptions(User $actor): array
    {
        if (UserType::Agency !== $actor->getUserType() || null === $actor->getAgency()) {
            return [];
        }

        $own = self::actorBranch($actor);

        /** @var list<Branch> $branches */
        $branches = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Branch::class, 'b')
            ->andWhere('b.agency = :agency')
            ->andWhere('b.status <> :closed')
            ->setParameter('agency', $actor->getAgency())
            ->setParameter('closed', BranchStatus::Closed->value)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();

        $options = [];
        foreach ($branches as $branch) {
            if (null === $own || $branch->getId() === $own->getId()) {
                $options[] = ['code' => (string) $branch->getCode(), 'name' => (string) $branch->getName()];
            }
        }

        return $options;
    }

        // Dans src/Service/UserManagementService.php

    /**
     * @return list<array{code: string, name: string, description: string}>
     */
    public function assignableRoles(User $actor): array
    {
        $scope = self::scopeOf($actor);
        $protectedRole = \App\Security\AccessCatalog::protectedRole($scope);

        // Vérifie si l'acteur est l'administrateur suprême de sa portée
        $isSouverain = \in_array($protectedRole, $actor->getRoles(), true)
            || \in_array('ROLE_GENERAL_MANAGER', $actor->getRoles(), true);

        // Récupération de tous les rôles du catalogue pour la portée actuelle
        /** @var list<Role> $roles */
        $roles = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Role::class, 'r')
            ->andWhere('r.scope = :scope')
            ->andWhere('r.isActive = :yes')
            ->setParameter('scope', $scope->value)
            ->setParameter('yes', true)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        $mine = $this->permissions->permissionsOf($actor);

        foreach ($roles as $role) {
            // Règle d'or : On ne peut jamais ré-attribuer le rôle d'administration suprême via cet écran
            if (\App\Security\AccessCatalog::isAdminRole($role->getCode())) {
                continue;
            }

            // Si l'acteur est un admin/souverain, on lui donne accès à tous les rôles inférieurs d'office.
            // Sinon (ex: ROLE_MANAGER), on applique le contrôle strict anti-élévation de privilèges.
            if (!$isSouverain) {
                $rolePermissions = $this->permissions->permissionsOfRole($role);
                if ([] !== array_diff($rolePermissions, $mine)) {
                    continue;
                }
            }

            $out[] = [
                'code' => (string) $role->getCode(),
                'name' => (string) $role->getName(),
                'description' => (string) $role->getDescription()
            ];
        }

        return $out;
    }


    // ------------------------------------------------------------------ BLOC 3 : ÉCRITURE & MUTATIONS

        // ------------------------------------------------------------------ BLOC 3 : ÉCRITURE & MUTATIONS

    /**
     * @throws AgentManagementException
     */
    public function create(User $actor, AgentData $data): User
    {
        return $this->em->wrapInTransaction(function () use ($actor, $data): User {
            $scope = self::scopeOf($actor);
            $errors = [];
            $email = mb_strtolower(trim((string) $data->email));

            if ($this->emailTaken($email, null)) {
                $errors['email'] = 'Cette adresse email est déjà utilisée.';
            }

            $role = $this->resolveRole($actor, $scope, $data->roleCode, null, $errors);
            $branch = Scope::Agency === $scope ? $this->resolveBranch($actor, $data->branchCode, $errors) : null;

            if ([] !== $errors) {
                throw new AgentManagementException($errors);
            }

            // Confiance absolue au Voter qui a validé le droit d'activation en amont
            $agent = (new User())
                ->setFirstname(trim((string) $data->firstname))
                ->setLastname(trim((string) $data->lastname))
                ->setPhone($this->normalizePhone((string) $data->phone))
                ->setEmail($email)
                ->setUserType(Scope::Platform === $scope ? UserType::Platform : UserType::Agency)
                ->setAgency(Scope::Agency === $scope ? $actor->getAgency() : null)
                ->setBranch($branch)
                ->setCode($this->uniqueCode())
                ->setIsActive('1' === $data->isActive);

            $agent->setPassword($this->passwordHasher->hashPassword($agent, (string) $data->password));
            $this->em->persist($agent);

            $this->assign($agent, $role, $branch, $actor);

            return $agent;
        });
    }

    /**
     * @throws AgentManagementException
     */
    public function update(User $actor, User $agent, AgentData $data): void
    {
        $this->em->wrapInTransaction(function () use ($actor, $agent, $data): void {
            $scope = self::scopeOf($actor);
            $errors = [];
            $email = mb_strtolower(trim((string) $data->email));

            if ($this->emailTaken($email, $agent)) {
                $errors['email'] = 'Cette adresse email est déjà utilisée.';
            }

            $isSelf = $agent->getId() === $actor->getId();
            $role = null;
            $branch = null;
            $current = $this->currentAssignment($agent);

            if (!$isSelf) {
                $role = $this->resolveRole($actor, $scope, $data->roleCode, $current?->getRole()?->getCode(), $errors);
                $branch = Scope::Agency === $scope ? $this->resolveBranch($actor, $data->branchCode, $errors) : null;
            }

            if ([] !== $errors) {
                throw new AgentManagementException($errors);
            }

            $agent->setFirstname(trim((string) $data->firstname))
                ->setLastname(trim((string) $data->lastname))
                ->setPhone($this->normalizePhone((string) $data->phone))
                ->setEmail($email);

            // ✅ AJOUT : Si l'administrateur a saisi un nouveau mot de passe, on le écrase
            if (null !== $data->password && '' !== trim((string) $data->password)) {
                $agent->setPassword($this->passwordHasher->hashPassword($agent, (string) $data->password));
            }


            if ($isSelf) {
                return;
            }

            $agent->setIsActive('1' === $data->isActive);

            $roleChanged = null === $current || $current->getRole()?->getId() !== $role?->getId();
            $branchChanged = Scope::Agency === $scope && $current?->getBranch()?->getId() !== $branch?->getId();

            if ($roleChanged || $branchChanged) {
                $current?->setRevokedAt(new \DateTimeImmutable())->setRevokedBy($actor);
                $agent->setBranch($branch);
                $this->assign($agent, $role, $branch, $actor);
            }
        });
    }


    // ------------------------------------------------------------------ BLOC 4 : UTILITAIRES INTERNES

    public function findAgent(User $actor, string $code): ?User
    {
        return $this->perimeter($actor)
            ->select('u', 'b')
            ->leftJoin('u.branch', 'b')
            ->andWhere('u.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function currentAssignment(User $agent): ?UserRole
    {
        return $this->currentAssignments([$agent])[(int) $agent->getId()] ?? null;
    }

    public function fill(AgentData $data, User $agent): void
    {
        $data->firstname = $agent->getFirstname();
        $data->lastname = $agent->getLastname();
        $data->email = $agent->getEmail();
        $data->phone = $agent->getPhone();
        $data->branchCode = $agent->getBranch()?->getCode();
        $data->roleCode = $this->currentAssignment($agent)?->getRole()?->getCode();
        $data->isActive = $agent->isActive() ? '1' : '0';
    }

    public function agentView(User $agent): array
    {
        $role = $this->currentAssignment($agent)?->getRole();

        /** @var list<UserRole> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('ur', 'r', 'rb', 'ab', 'rv')
            ->from(UserRole::class, 'ur')
            ->join('ur.role', 'r')
            ->leftJoin('ur.branch', 'rb')
            ->leftJoin('ur.assignedBy', 'ab')
            ->leftJoin('ur.revokedBy', 'rv')
            ->andWhere('IDENTITY(ur.user) = :user')
            ->setParameter('user', $agent->getId())
            ->orderBy('ur.assignedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'firstname' => (string) $agent->getFirstname(),
            'lastname' => (string) $agent->getLastname(),
            'email' => (string) $agent->getEmail(),
            'code' => (string) $agent->getCode(),
            'phone' => (string) $agent->getPhone(),
            'isOnline' => $agent->isOnline(),
            'isActive' => $agent->isActive(),
            'branchName' => $agent->getBranch()?->getName(),
            'role' => [
                'name' => $role?->getName() ?? 'Aucune fonction',
                'code' => (string) $role?->getCode(),
                'description' => (string) $role?->getDescription(),
                'isActive' => $role?->isActive() ?? false,
            ],
            'career_history' => array_map(static fn(UserRole $ur): array => [
                'roleName' => (string) $ur->getRole()?->getName(),
                'branchName' => $ur->getBranch()?->getName(),
                'assignedAt' => $ur->getAssignedAt(),
                'assignedBy' => $ur->getAssignedBy()?->getFullName(),
                'revokedAt' => $ur->getRevokedAt(),
                'revokedBy' => $ur->getRevokedBy()?->getFullName(),
            ], $rows),
        ];
    }

    private function perimeter(User $actor): QueryBuilder
    {
        $scope = self::scopeOf($actor);

        $qb = $this->em->createQueryBuilder()
            ->from(User::class, 'u')
            ->andWhere('u.userType = :type')
            ->andWhere('u.email <> :system')
            ->setParameter('type', (Scope::Platform === $scope ? UserType::Platform : UserType::Agency)->value)
            ->setParameter('system', SystemActor::EMAIL);

        if (Scope::Agency === $scope) {
            $qb->andWhere('u.agency = :agency')->setParameter('agency', $actor->getAgency());
            if (null !== ($branch = self::actorBranch($actor))) {
                $qb->andWhere('u.branch = :actorBranch')->setParameter('actorBranch', $branch);
            }
        }

        return $qb;
    }

    private function currentAssignments(array $users): array
    {
        if ([] === $users) {
            return [];
        }

        /** @var list<UserRole> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('ur', 'r', 'rb')
            ->from(UserRole::class, 'ur')
            ->join('ur.role', 'r')
            ->leftJoin('ur.branch', 'rb')
            ->andWhere('IDENTITY(ur.user) IN (:users)')
            ->andWhere('ur.revokedAt IS NULL')
            ->setParameter('users', array_map(static fn(User $u): int => (int) $u->getId(), $users))
            ->orderBy('ur.assignedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->getUser()?->getId()] ??= $row;
        }

        return $map;
    }

    private function assign(User $agent, ?Role $role, ?Branch $branch, User $actor): void
    {
        if (null === $role) {
            return;
        }
        $this->em->persist((new UserRole())
            ->setUser($agent)
            ->setRole($role)
            ->setAgency($agent->getAgency())
            ->setBranch($branch)
            ->setAssignedBy($actor));
    }

    private function resolveRole(User $actor, Scope $scope, ?string $code, ?string $currentCode, array &$errors): ?Role
    {
        $role = null === $code || '' === $code ? null : $this->em->getRepository(Role::class)->findOneBy(['code' => $code]);
        if (!$role instanceof Role || !$role->isActive() || $role->getScope() !== $scope) {
            $errors['role_code'] = 'Cette fonction n\'est pas disponible.';
            return null;
        }
        if ($code !== $currentCode && !\in_array($code, array_column($this->assignableRoles($actor), 'code'), true)) {
            $errors['role_code'] = 'Vous ne pouvez pas attribuer cette fonction.';
            return null;
        }
        return $role;
    }

    private function resolveBranch(User $actor, ?string $code, array &$errors): ?Branch
    {
        $own = self::actorBranch($actor);
        if (null === $code || '' === $code) {
            if (null !== $own) {
                $errors['branch_code'] = 'Vous devez affecter l\'agent à votre succursale.';
            }
            return null;
        }
        $branch = $this->em->getRepository(Branch::class)->findOneBy(['agency' => $actor->getAgency(), 'code' => $code]);
        if (!$branch instanceof Branch || BranchStatus::Closed === $branch->getStatus()) {
            $errors['branch_code'] = 'Cette succursale est introuvable ou fermée.';
            return null;
        }
        if (null !== $own && $branch->getId() !== $own->getId()) {
            $errors['branch_code'] = 'Vous ne pouvez affecter qu\'à votre succursale.';
            return null;
        }
        return $branch;
    }

    private function emailTaken(string $email, ?User $except): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', $email);

        if (null !== $except) {
            $qb->andWhere('u.id <> :except')->setParameter('except', $except->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function normalizePhone(string $phone): string
    {
        return substr((string) preg_replace('/[^\d+]/', '', $phone), 0, 30);
    }

    private function uniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $repository = $this->em->getRepository(User::class);

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $code = 'USR-';
            for ($i = 0; $i < 8; ++$i) {
                $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }
            if (null === $repository->findOneBy(['code' => $code])) {
                return $code;
            }
        }
        throw new AgentManagementException(['global' => 'Erreur lors de la génération du code unique.']);
    }
} // Fin de la classe
