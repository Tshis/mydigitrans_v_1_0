<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Role;
use App\Entity\RolePermission;
use App\Enum\Scope;
use App\Security\AccessCatalog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Consultation des rôles du système (lecture seule) : liste d'une portée et permissions d'un rôle.
 */
final class RoleDirectory
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return list<array{name: string, code: string, description: string, scope: string, isActive: bool, permissions_count: int}>
     */
    public function rolesOf(Scope $scope): array
    {
        /** @var list<Role> $roles */
        $roles = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Role::class, 'r')
            ->andWhere('r.scope = :scope')
            ->setParameter('scope', $scope->value)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($this->em->createQueryBuilder()
            ->select('IDENTITY(rp.role) AS roleId', 'COUNT(rp.id) AS total')
            ->from(RolePermission::class, 'rp')
            ->andWhere('rp.revokedAt IS NULL')
            ->andWhere('rp.agency IS NULL')
            ->groupBy('rp.role')
            ->getQuery()
            ->getArrayResult() as $row) {
            $counts[(int) $row['roleId']] = (int) $row['total'];
        }

        return array_map(static fn (Role $r): array => [
            'name' => (string) $r->getName(),
            'code' => (string) $r->getCode(),
            'description' => (string) $r->getDescription(),
            'scope' => $r->getScope()->value,
            'isActive' => $r->isActive(),
            'permissions_count' => $counts[(int) $r->getId()] ?? 0,
        ], $roles);
    }

    public function find(Scope $scope, string $code): ?Role
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['code' => $code, 'scope' => $scope]);

        return $role instanceof Role ? $role : null;
    }

    /**
     * Permissions d'un rôle, regroupées par module.
     *
     * @return list<array{module: string, label: string, icon: string, permissions: list<array{code: string, name: string}>}>
     */
    public function permissionsByModule(Role $role): array
    {
        /** @var list<RolePermission> $links */
        $links = $this->em->createQueryBuilder()
            ->select('rp', 'p')
            ->from(RolePermission::class, 'rp')
            ->join('rp.permission', 'p')
            ->andWhere('IDENTITY(rp.role) = :role')
            ->andWhere('rp.revokedAt IS NULL')
            ->andWhere('rp.agency IS NULL')
            ->andWhere('p.scope = :scope')
            ->setParameter('role', $role->getId())
            ->setParameter('scope', $role->getScope()->value)
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
