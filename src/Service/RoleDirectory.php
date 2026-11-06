<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agency;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Enum\Scope;
use App\Security\AccessCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Consultation des rôles du système (lecture seule) : liste d'une portée et permissions d'un rôle.
 */
final class RoleDirectory
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @return list<array{id: int, name: string, code: string, description: string, scope: string, isActive: bool, permissions_count: int}>
     */
    public function rolesOf(Scope $scope, ?Agency $currentAgency = null): array
    {
        /** @var list<Role> $roles */
        $roles = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Role::class, 'r')
            ->andWhere('r.scope = :scope')
            ->andWhere('r.code NOT IN (:excludedRoles)')
            ->andWhere('r.code NOT LIKE :noAdmin')
            ->setParameter('scope', $scope->value)
            ->setParameter('excludedRoles', ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'])
            ->setParameter('noAdmin', '%_ADMIN%')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];

        /** @var QueryBuilder $qbCounts */
        $qbCounts = $this->em->createQueryBuilder()
            ->select('IDENTITY(rp.role) AS roleId', 'COUNT(rp.id) AS total')
            ->from(RolePermission::class, 'rp')
            ->andWhere('rp.revokedAt IS NULL');

        // 🟢 FIX : On compte les permissions globales (NULL) OU celles surchargées pour cette agence spécifique
        if (Scope::Agency === $scope && $currentAgency !== null) {
            $qbCounts->andWhere('rp.agency IS NULL OR rp.agency = :agency')
                ->setParameter('agency', $currentAgency);
        } else {
            $qbCounts->andWhere('rp.agency IS NULL');
        }

        foreach ($qbCounts->groupBy('rp.role')->getQuery()->getArrayResult() as $row) {
            $counts[(int) $row['roleId']] = (int) $row['total'];
        }

        return array_map(static function (Role $r) use ($counts): array {
            $scopeEnum = $r->getScope();
            $scopeStr = $scopeEnum instanceof Scope ? $scopeEnum->value : (string) $scopeEnum;

            return [
                'id'                => (int) $r->getId(),
                'name'              => (string) $r->getName(),
                'code'              => (string) $r->getCode(),
                'description'       => (string) $r->getDescription(),
                'scope'             => $scopeStr,
                'isActive'          => $r->isActive(),
                'permissions_count' => $counts[(int) $r->getId()] ?? 0,
            ];
        }, $roles);
    }

    public function find(Scope $scope, string $code): ?Role
    {
        return $this->em->getRepository(Role::class)->findOneBy(['code' => $code, 'scope' => $scope]);
    }

    public function permissionsByModule(Role $role): array
    {
        /** @var list<RolePermission> $links */
        $links = $this->em->createQueryBuilder()
            ->select('rp', 'p')
            ->from(RolePermission::class, 'rp')
            ->join('rp.permission', 'p')
            ->andWhere('IDENTITY(rp.role) = :role')
            ->andWhere('rp.revokedAt IS NULL')
            ->andWhere('p.scope = :scope')
            ->setParameter('role', $role->getId())
            ->setParameter('scope', $role->getScope() instanceof Scope ? $role->getScope()->value : (string) $role->getScope())
            ->orderBy('p.module', 'ASC')
            ->addOrderBy('p.code', 'ASC')
            ->getQuery()
            ->getResult();

        $groups = [];
        foreach ($links as $link) {
            $permission = $link->getPermission();
            $module = (string) $permission?->getModule();

            $groups[$module] ??= ['module' => $module] + AccessCatalog::moduleMeta($role->getScope(), $module) + ['permissions' => []];
            $groups[$module]['permissions'][] = ['code' => (string) $permission?->getCode(), 'name' => (string) $permission?->getName()];
        }

        return array_values($groups);
    }
}
