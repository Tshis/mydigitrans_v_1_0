<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

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
        return Scope::Agency;
    }

    #[Route('/admin/agency/roles', name: 'admin_agency_role_index', methods: ['GET'])]
    public function index(RoleService $roles): Response
    {
        return $this->listAction($roles);
    }

    #[Route('/admin/agency/role/add', name: 'admin_agency_role_add', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        PermissionRepository $permissionRepository,
        EntityManagerInterface $em,
        PermissionResolver $permissionResolver // 🟢 Injection requise par le parent
    ): Response {
        $role = new Role();
        $role->setScope($this->scope());
        $role->setIsActive(true);
        $role->setAgency($this->actor()->getAgency()); // Cloisonnement multi-tenant de l'entité

        return $this->formAction($request, $permissionRepository, $em, $permissionResolver, $role);
    }

    #[Route('/admin/agency/role/edit/{id}', name: 'admin_agency_role_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Role $role,
        PermissionRepository $permissionRepository,
        EntityManagerInterface $em,
        PermissionResolver $permissionResolver // 🟢 Injection requise par le parent
    ): Response {
        // Sécurité Multi-tenant d'agence
        if ($role->getAgency() !== null && $role->getAgency()->getId() !== $this->actor()->getAgency()->getId()) {
            throw $this->createAccessDeniedException('Ce rôle n’appartient pas à votre compagnie.');
        }

        return $this->formAction($request, $permissionRepository, $em, $permissionResolver, $role);
    }
}
