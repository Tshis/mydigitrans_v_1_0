<?php

namespace App\Controller\web\admin\platform;

use App\Dto\admin\AgentData;
use App\Form\AgentType;
use App\Entity\User;
use App\Enum\UserType;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/admin/platform/agents', name: 'admin_platform_agent_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW);

        // Liste uniquement le personnel interne du SaaS [MCD 3]
        $users = $em->getRepository(User::class)->findBy(['userType' => 'platform']);

        return $this->render('admin/agent/index.html.twig', [
            'page' => 'agent',
            'users' => $users
        ]);
    }

    #[Route('/admin/platform/agent/add', name: 'admin_platform_agent_add')]
    #[Route('/admin/platform/agent/{code}/edit', name: 'admin_platform_agent_edit')]
    public function add_and_edit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ?string $code = null
    ): Response {

        $isEdit = $code !== null;
        $agentEntity = $isEdit ? $em->getRepository(User::class)->findOneBy(['code' => $code]) : new User();

        if ($isEdit && !$agentEntity) {
            throw $this->createNotFoundException("Utilisateur plateforme introuvable.");
        }

        if ($isEdit) {
            $this->denyAccessUnlessGranted(UserVoter::WRITE, $agentEntity);
        }

        $agentData = new AgentData();
        if ($isEdit) {
            $agentData->firstname = $agentEntity->getFirstname();
            $agentData->lastname = $agentEntity->getLastname();
            $agentData->email = $agentEntity->getEmail();
            $agentData->phone = $agentEntity->getPhone();
            $agentData->isActive = $agentEntity->isActive() ? '1' : '0';
        }

        // Rôles spécifiques au scope Plateforme
        $platformRoles = [
            ['code' => 'ROLE_PLATFORM_ACCOUNTANT', 'name' => 'Comptable plateforme', 'description' => 'Facturation et commissions'],
            ['code' => 'ROLE_PLATFORM_CASHIER', 'name' => 'Caissier plateforme', 'description' => 'Encaissement abonnements'],
            ['code' => 'ROLE_PLATFORM_SUPPORT', 'name' => 'Support plateforme', 'description' => 'Accompagnement agences']
        ];

        $form = $this->createForm(AgentType::class, $agentData, [
            'is_edit' => $isEdit,
            'roles' => $platformRoles,
            'with_branch' => false, // Pas de succursale d'agence pour l'équipe du SaaS
            'branches' => [],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(UserVoter::WRITE, $agentData);

            $agentEntity->setFirstname($agentData->firstname);
            $agentEntity->setLastname($agentData->lastname);
            $agentEntity->setEmail($agentData->email);
            $agentEntity->setPhone($agentData->phone);
            $agentEntity->setUserType(UserType::Platform); // Isolation stricte [MCD 3]
            $agentEntity->setBranch(null);
            $agentEntity->setAgency(null);

            if (!$isEdit) {
                $agentEntity->setPassword($passwordHasher->hashPassword($agentEntity, $agentData->password));
                $agentEntity->setCode('plt-' . random_int(1000, 9999));
            }

            // Seul le rôle SUPER_ADMIN gère le cycle actif des comptes plateforme via le voter
            $agentEntity->setIsActive($agentData->isActive === '1');

            $em->persist($agentEntity);
            $em->flush();

            $this->addFlash('success', 'Compte membre plateforme configuré avec succès.');
            return $this->redirectToRoute('admin_platform_agent_index');
        }

        return $this->render('admin/platform_agent/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => $isEdit,
            'agent' => $agentEntity
        ]);
    }


    #[Route('/admin/platform/agent/{code}/details', name: 'admin_platform_agent_show')]
    public function show(string $code, EntityManagerInterface $em): Response
    {
        // 1. Récupération de l'utilisateur ayant le scope plateforme [MCD 3]
        $agentEntity = $em->getRepository(User::class)->findOneBy([
            'code' => $code,
            'userType' => UserType::Platform // Isolation stricte plateforme [MCD 3]
        ]);

        if (!$agentEntity) {
            throw $this->createNotFoundException("Utilisateur plateforme introuvable.");
        }

        // 2. Contrôle de sécurité via le UserVoter
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $agentEntity);

        // 3. Rendu du template dédié au personnel SaaS
        return $this->render('admin/agent/show.html.twig', [
            'page' => 'agent',
            'agent' => $agentEntity
        ]);
    }
}
