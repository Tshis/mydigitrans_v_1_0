<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\Scope;
use App\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service central de résolution des permissions réelles d'un utilisateur et d'un rôle.
 * Centralise les accès pour l'ensemble du système RBAC de MyDigitrans.
 */
final class PermissionResolver
{
    /** @var array<int, array<string, true>> Cache des permissions par ID d'utilisateur */
    private array $cache = [];

    /** @var array<int, list<string>> Cache des permissions par ID de rôle */
    private array $roleCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Déduit la portée (Scope) d'administration liée au type de compte de l'utilisateur.
     */
    public static function scopeOf(User $user): ?Scope
    {
        return match ($user->getUserType()) {
            UserType::Platform => Scope::Platform,
            UserType::Agency => Scope::Agency,
            default => null,
        };
    }

    /**
     * Vérifie si un utilisateur possède une permission spécifique.
     */
    public function has(User $user, string $permission): bool
    {
        return isset($this->forUser($user)[$permission]);
    }

    /**
     * Retourne la liste à plat des codes de permissions détenus par un utilisateur.
     *
     * @return list<string>
     */
    public function permissionsOf(User $user): array
    {
        return array_keys($this->forUser($user));
    }

    /**
     * Extrait les permissions par défaut associées à un rôle système (sans distinction d'agence).
     * Requis par le Voter pour analyser les droits hiérarchiques.
     *
     * @return list<string>
     */
    public function permissionsOfRole(Role $role): array
    {
        $id = (int) $role->getId();

        if (isset($this->roleCache[$id])) {
            return $this->roleCache[$id];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('p.code AS code')
            ->from(RolePermission::class, 'rp')
            ->join('rp.permission', 'p')
            ->andWhere('IDENTITY(rp.role) = :role')
            ->andWhere('rp.revokedAt IS NULL')
            ->andWhere('rp.agency IS NULL')
            ->andWhere('p.scope = :scope')
            ->setParameter('role', $id)
            ->setParameter('scope', $role->getScope()->value)
            ->getQuery()
            ->getArrayResult();

        return $this->roleCache[$id] = array_map(static fn(array $row): string => (string) $row['code'], $rows);
    }

    /**
     * Construit la carte complète des permissions actives d'un utilisateur.
     *
     * @return array<string, true>
     */
    private function forUser(User $user): array
    {
        $id = (int) $user->getId();

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $scope = self::scopeOf($user);
        $agency = $user->getAgency();

        // Garde-fou : si pas de scope, compte inactif ou scope agence sans agence liée, aucun droit.
        if (null === $scope || !$user->isActive() || (Scope::Agency === $scope && null === $agency)) {
            return $this->cache[$id] = [];
        }

        // 👑 COURT-CIRCUIT ADMINISTRATIF : Les rôles d'administration système possèdent structurellement tout.
        $protectedRole = AccessCatalog::protectedRole($scope);
        if (\in_array($protectedRole, $user->getRoles(), true)) {
            $allPermissions = [];
            foreach (array_keys(AccessCatalog::permissions($scope)) as $code) {
                $allPermissions[$code] = true;
            }
            return $this->cache[$id] = $allPermissions;
        }

        // Extraction SQL/Doctrine classique via la table d'attribution physique UserRole
        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT p.code AS code')
            ->from(RolePermission::class, 'rp')
            ->join('rp.permission', 'p')
            ->join('rp.role', 'r')
            ->join(UserRole::class, 'ur', 'WITH', 'ur.role = r')
            ->andWhere('IDENTITY(ur.user) = :user')
            ->andWhere('ur.revokedAt IS NULL')
            ->andWhere('r.isActive = :yes')
            ->andWhere('r.scope = :scope')
            ->andWhere('p.scope = :scope')
            ->andWhere('rp.revokedAt IS NULL')
            ->andWhere('(rp.branch IS NULL OR rp.branch = ur.branch)')
            ->setParameter('user', $id)
            ->setParameter('yes', true)
            ->setParameter('scope', $scope->value);

        if (Scope::Agency === $scope) {
            $qb->andWhere('IDENTITY(ur.agency) = :agency')
                ->andWhere('(rp.agency IS NULL OR IDENTITY(rp.agency) = :agency)')
                ->setParameter('agency', $agency?->getId());
        } else {
            $qb->andWhere('ur.agency IS NULL')->andWhere('rp.agency IS NULL');
        }

        $codes = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $codes[(string) $row['code']] = true;
        }

        return $this->cache[$id] = $codes;
    }
}
