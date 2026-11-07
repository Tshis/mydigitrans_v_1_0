<?php

declare(strict_types=1);

namespace App\Controller\web\admin\platform;

use App\Controller\web\admin\AbstractRoleAdminController;
use App\Entity\Role;
use App\Enum\Scope;
use App\Repository\PermissionRepository;
use App\Security\PermissionResolver; // 🟢 Import du Resolver
use App\Service\RoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RoleController extends AbstractRoleAdminController
{
    protected function scope(): Scope
    {
        return Scope::Platform;
    }

    #[Route('/admin/platform/roles', name: 'admin_platform_role_index', methods: ['GET'])]
    public function index(RoleService $roles): Response
    {
        return $this->listAction($roles);
    }

    #[Route('/admin/platform/role/add', name: 'admin_platform_role_add', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        PermissionRepository $permissionRepository,
        EntityManagerInterface $em,
        PermissionResolver $permissionResolver // 🟢 Injection requise par le parent
    ): Response {
        $role = new Role();
        $role->setScope($this->scope());
        $role->setIsActive(true);
        $role->setAgency(null); // Portée globale Platform Système

        return $this->formAction($request, $permissionRepository, $em, $permissionResolver, $role);
    }

    #[Route('/admin/platform/role/edit/{id}', name: 'admin_platform_role_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Role $role,
        PermissionRepository $permissionRepository,
        EntityManagerInterface $em,
        PermissionResolver $permissionResolver // 🟢 Injection requise par le parent
    ): Response {
        // Sécurité d'isolation Système
        if ($role->getAgency() !== null) {
            throw $this->createAccessDeniedException('Cet écran ne permet pas de modifier les rôles spécifiques des agences clientes.');
        }

        return $this->formAction($request, $permissionRepository, $em, $permissionResolver, $role);
    }
}
