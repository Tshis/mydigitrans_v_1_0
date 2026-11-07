<?php

declare(strict_types=1);

namespace App\Controller\web\admin\platform;

use App\Controller\web\admin\AbstractUserAdminController;
use App\Entity\User;
use App\Enum\Scope;
use App\Service\UserManagementService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class UserController extends AbstractUserAdminController
{
    protected function scope(): Scope
    {
        return Scope::Platform;
    }

    protected function routePrefix(): string
    {
        return 'admin_platform';
    }

    #[Route('/admin/platform/agents', name: 'admin_platform_agent_index', methods: ['GET'])]
    public function index(Request $request, UserManagementService $users): Response
    {
        return $this->listAction($request, $users);
    } //index

    #[Route('/admin/platform/agent/add', name: 'admin_platform_agent_add', methods: ['GET', 'POST'])]
    #[Route('/admin/platform/agent/{code}/edit', name: 'admin_platform_agent_edit', methods: ['GET', 'POST'])]
    public function add_and_edit(
        Request $request,
        UserManagementService $users,
        TokenStorageInterface $tokenStorage,
        #[MapEntity(mapping: ['code' => 'code'])] ?User $agent = null
    ): Response {
        $actor = $this->actor(); // Utilise la validation unifiée stricte d'AbstractAdminController

        if ($request->isMethod('POST')) {
            $response = $this->formAction($request, $users, $agent ? $agent->getCode() : null);

            // Règle d'auto-rafraîchissement à chaud de la session du Super Admin s'il s'auto-édite
            if ($agent && $agent->getCode() === $actor->getCode() && $response->isRedirection()) {
                $token = new UsernamePasswordToken($agent, 'main', $agent->getRoles());
                $tokenStorage->setToken($token);
                $request->getSession()->set('_security_main', serialize($token));
            }

            return $response;
        }

        return $this->formAction($request, $users, $agent ? $agent->getCode() : null);
    } //add_and_edit

    #[Route('/admin/platform/agent/{code}/details', name: 'admin_platform_agent_show', methods: ['GET'])]
    public function show(
        UserManagementService $users,
        #[MapEntity(mapping: ['code' => 'code'])] User $agent
    ): Response {
        return $this->showAction($users, $agent->getCode());
    } //show
}
