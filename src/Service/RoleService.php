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
 * Service central de gestion et de consultation des rôles et des fonctions système.
 */
final class RoleService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @return list<array{id: int, name: string, code: string, description: string, scope: string, isActive: bool, permissions_count: int}>
     */
    public function rolesOf(Scope $scope, ?Agency $currentAgency = null): array
    {
        $excludedSystemRoles = [AccessCatalog::SUPER_ADMIN, AccessCatalog::AGENCY_ADMIN];

        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Role::class, 'r')
            ->andWhere('r.scope = :scope')
            ->andWhere('r.code NOT IN (:excludedRoles)')
            ->setParameter('scope', $scope->value)
            ->setParameter('excludedRoles', $excludedSystemRoles);

        // 🟢 FIX ÉTANCHÉITÉ MULTI-TENANT : Utilisation d'une structure de paramètres séparés
        // Doctrine résout r.agency de manière safe si on sépare la clause d'appartenance de la clause système NULL.
        if (Scope::Agency === $scope && $currentAgency !== null) {
            $qb->andWhere('r.agency IS NULL OR r.agency = :currentAgency')
                ->setParameter('currentAgency', $currentAgency);
        } else {
            $qb->andWhere('r.agency IS NULL');
        }

        /** @var list<Role> $roles */
        $roles = $qb->orderBy('r.name', 'ASC')->getQuery()->getResult();

        $counts = [];

        /** @var QueryBuilder $qbCounts */
        $qbCounts = $this->em->createQueryBuilder()
            ->select('IDENTITY(rp.role) AS roleId', 'COUNT(rp.id) AS total')
            ->from(RolePermission::class, 'rp')
            ->andWhere('rp.revokedAt IS NULL');

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
        return $this->em->getRepository(Role::class)->findOneBy(['code' => $code, 'scope' => $scope->value]);
    }

    public function permissionsByModule(Role $role): array
    {
        $roleScope = $role->getScope();
        $scopeEnum = $roleScope instanceof Scope ? $roleScope : Scope::from((string) $roleScope);

        /** @var list<RolePermission> $links */
        $links = $this->em->createQueryBuilder()
            ->select('rp', 'p')
            ->from(RolePermission::class, 'rp')
            ->join('rp.permission', 'p')
            ->andWhere('IDENTITY(rp.role) = :role')
            ->andWhere('rp.revokedAt IS NULL')
            ->andWhere('p.scope = :scope')
            ->setParameter('role', $role->getId())
            ->setParameter('scope', $scopeEnum->value)
            ->orderBy('p.module', 'ASC')
            ->addOrderBy('p.code', 'ASC')
            ->getQuery()
            ->getResult();

        $groups = [];
        foreach ($links as $link) {
            $permission = $link->getPermission();
            $module = (string) $permission?->getModule();

            $groups[$module] ??= ['module' => $module] + AccessCatalog::moduleMeta($scopeEnum, $module) + ['permissions' => []];
            $groups[$module]['permissions'][] = [
                'code' => (string) $permission?->getCode(),
                'name' => (string) $permission?->getName()
            ];
        }

        return array_values($groups);
    }
}
