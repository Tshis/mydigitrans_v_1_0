<?php

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractRoleAdminController;
use App\Enum\Scope;
use App\Service\RoleDirectory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RoleController extends AbstractRoleAdminController
{
    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/roles', name: 'admin_agency_role_index')]
    public function index(RoleDirectory $roles): Response
    {
        return $this->listAction($roles);
    } //index

    #[Route('/admin/agency/role/{code}', name: 'admin_agency_role_show')]
    public function show(RoleDirectory $roles, string $code): Response
    {
        return $this->showAction($roles, $code);
    } //show
}
