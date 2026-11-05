<?php

namespace App\Controller\web\admin\platform;

use App\Controller\web\admin\AbstractRoleAdminController;
use App\Enum\Scope;
use App\Service\RoleDirectory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RoleController extends AbstractRoleAdminController
{
    protected function scope(): Scope
    {
        return Scope::Platform;
    }

    #[Route('/admin/platform/roles', name: 'admin_platform_role_index')]
    public function index(RoleDirectory $roles): Response
    {
        return $this->listAction($roles);
    } //index

    #[Route('/admin/platform/role/{code}', name: 'admin_platform_role_show')]
    public function show(RoleDirectory $roles, string $code): Response
    {
        return $this->showAction($roles, $code);
    } //show
}
