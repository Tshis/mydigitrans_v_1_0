<?php

namespace App\Controller\web\admin\agency;

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
        return Scope::Agency;
    }

    #[Route('/admin/agency/agents', name: 'admin_agency_agent_index')]
    public function index(Request $request, UserManagementService $users): Response
    {
        return $this->listAction($request, $users);
    } //index

    #[Route('/admin/agency/agent/add', name: 'admin_agency_agent_add', methods: ['GET', 'POST'])]
    #[Route('/admin/agency/agent/{code}/edit', name: 'admin_agency_agent_edit', methods: ['GET', 'POST'])]
    public function add_and_edit(Request $request, UserManagementService $users, ?string $code = null): Response
    {
        return $this->formAction($request, $users, $code);
    } //add_and_edit

    #[Route('/admin/agency/agent/{code}/details', name: 'admin_agency_agent_show')]
    public function show(UserManagementService $users, string $code): Response
    {
        return $this->showAction($users, $code);
    } //show
}
