<?php

namespace App\Controller\web\admin\platform;

use App\Controller\web\admin\AbstractUserAdminController;
use App\Enum\Scope;
use App\Service\UserManagementService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractUserAdminController
{
    protected function scope(): Scope
    {
        return Scope::Platform;
    }

    #[Route('/admin/platform/agents', name: 'admin_platform_agent_index')]
    public function index(Request $request, UserManagementService $users): Response
    {
        return $this->listAction($request, $users);
    } //index

    #[Route('/admin/platform/agent/add', name: 'admin_platform_agent_add', methods: ['GET', 'POST'])]
    #[Route('/admin/platform/agent/{code}/edit', name: 'admin_platform_agent_edit', methods: ['GET', 'POST'])]
    public function add_and_edit(Request $request, UserManagementService $users, ?string $code = null): Response
    {
        return $this->formAction($request, $users, $code);
    } //add_and_edit

    #[Route('/admin/platform/agent/{code}/details', name: 'admin_platform_agent_show')]
    public function show(UserManagementService $users, string $code): Response
    {
        return $this->showAction($users, $code);
    } //show
}
