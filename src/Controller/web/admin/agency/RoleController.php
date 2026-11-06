<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractRoleAdminController;
use App\Entity\Role;
use App\Enum\Scope;
use App\Repository\PermissionRepository;
use App\Service\RoleDirectory;
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
    public function index(RoleDirectory $roles): Response
    {
        return $this->listAction($roles);
    }

    #[Route('/admin/agency/role/add', name: 'admin_agency_role_add', methods: ['GET', 'POST'])]
    public function add(Request $request, PermissionRepository $permissionRepository, EntityManagerInterface $em): Response
    {
        return $this->formAction($request, $permissionRepository, $em);
    }

    #[Route('/admin/agency/role/edit/{id}', name: 'admin_agency_role_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Role $role, PermissionRepository $permissionRepository, EntityManagerInterface $em): Response
    {
        return $this->formAction($request, $permissionRepository, $em, $role);
    }
}
