<?php

namespace App\Controller\web\admin;

use App\Service\RoleDirectory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consultation des fonctions (rôles) du système : lecture seule, car les rôles sont fixés par le système.
 */
abstract class AbstractRoleAdminController extends AbstractAdminController
{
    protected function listAction(RoleDirectory $roles): Response
    {
        $this->actor();
        $this->denyAccessUnlessGranted('role.view');

        return $this->render('admin/role/index.html.twig', [
            'page' => 'job title',
            'route_prefix' => $this->routePrefix(),
            'roles' => $roles->rolesOf($this->scope()),
        ]);
    }

    protected function showAction(RoleDirectory $roles, string $code): Response
    {
        $this->actor();
        $this->denyAccessUnlessGranted('role.view');

        $role = $roles->find($this->scope(), $code) ?? throw $this->createNotFoundException('Fonction introuvable.');

        return $this->render('admin/role/show.html.twig', [
            'page' => 'job title',
            'route_prefix' => $this->routePrefix(),
            'role' => [
                'name' => (string) $role->getName(),
                'code' => (string) $role->getCode(),
                'description' => (string) $role->getDescription(),
                'isActive' => $role->isActive(),
            ],
            'modules' => $roles->permissionsByModule($role),
        ]);
    }
}
