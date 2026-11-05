<?php

namespace App\Controller\web\admin;

use App\Dto\admin\AgentData;
use App\Enum\Scope;
use App\Exception\AgentManagementException;
use App\Form\AgentType;
use App\Security\Voter\UserManagementVoter;
use App\Service\UserManagementService;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur d'administration pour la gestion des collaborateurs (générique).
 */
abstract class AbstractUserAdminController extends AbstractAdminController
{
    protected function listAction(Request $request, UserManagementService $users): Response
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::VIEW);
        $actor = $this->actor();

        $canFilterBranch = Scope::Agency === $this->scope() && null === UserManagementService::actorBranch($actor);
        $filters = [
            'search' => trim((string) $request->query->get('search', '')),
            'branch' => $canFilterBranch ? (string) $request->query->get('branch_code', '') : '',
            'status' => (string) $request->query->get('status', ''),
        ];

        return $this->render('admin/agent/index.html.twig', [
            'page' => 'agent',
            'route_prefix' => $this->routePrefix(),
            'is_agency_scope' => Scope::Agency === $this->scope(),
            'users' => $users->listAgents($actor, $filters),
            'branch_options' => $canFilterBranch ? $users->branchOptions($actor) : [],
            'filters' => $filters,
        ]);
    }

    protected function formAction(Request $request, UserManagementService $users, ?string $code): Response
    {
        $actor = $this->actor();
        $isEdit = null !== $code;
        $agent = null;
        $data = new AgentData();

        if ($isEdit) {
            $agent = $users->findAgent($actor, $code) ?? throw $this->createNotFoundException('Utilisateur introuvable.');
            $this->denyAccessUnlessGranted(UserManagementVoter::WRITE, $agent);
            $users->fill($data, $agent);
        } else {
            $this->denyAccessUnlessGranted(UserManagementVoter::WRITE, $data);
            $data->branchCode = UserManagementService::actorBranch($actor)?->getCode();
        }

        $canActivate = $this->isGranted(UserManagementVoter::ACTIVATE, $agent ?? $data);
        if (!$isEdit) {
            $data->isActive = $canActivate ? '1' : '0';
        }

        $isSelf = null !== $agent && $agent->getId() === $actor->getId();

        $form = $this->createForm(AgentType::class, $data, [
            'is_edit' => $isEdit,
            'identity_only' => $isSelf,
            'with_branch' => Scope::Agency === $this->scope(),
            'can_choose_general' => null === UserManagementService::actorBranch($actor),
            'can_activate' => $canActivate,
            'branches' => $users->branchOptions($actor),
            'roles' => $users->assignableRoles($actor),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($isEdit) {
                    $users->update($actor, $agent, $data);
                    $this->addFlash('success', sprintf('Le compte de %s %s a été mis à jour.', $data->firstname, $data->lastname));
                } else {
                    $this->denyAccessUnlessGranted(UserManagementVoter::WRITE, $data);
                    $users->create($actor, $data);
                    $this->addFlash('success', sprintf('Le compte de %s %s a été déployé.', $data->firstname, $data->lastname));
                }

                return $this->redirectToRoute($this->routePrefix() . '_agent_index');
            } catch (AgentManagementException $e) {
                foreach ($e->getErrors() as $field => $message) {
                    ($form->has($field) ? $form->get($field) : $form)->addError(new FormError($message));
                }
            }
        }

        return $this->render('admin/agent/form.html.twig', [
            'page' => 'agent',
            'isEdit' => $isEdit,
            'identity_only' => $isSelf,
            'form' => $form->createView(),
            'route_prefix' => $this->routePrefix(),
            'is_agency_scope' => Scope::Agency === $this->scope(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    protected function showAction(UserManagementService $users, string $code): Response
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::VIEW);
        $actor = $this->actor();

        $agent = $users->findAgent($actor, $code) ?? throw $this->createNotFoundException('Utilisateur introuvable.');

        return $this->render('admin/agent/show.html.twig', [
            'page' => 'agent',
            'route_prefix' => $this->routePrefix(),
            'agent' => $users->agentView($agent),
            'can_manage' => $this->isGranted(UserManagementVoter::WRITE, $agent),
        ]);
    }
}
