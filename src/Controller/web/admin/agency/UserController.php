<?php

namespace App\Controller\web\admin\agency;

use App\Dto\admin\AgentData;
use App\Entity\Branch;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\UserType;
use App\Form\AgentType;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/admin/agency/agents', name: 'admin_agency_agent_index')]
    public function index(EntityManagerInterface $em): Response
    {
        // 1. Autorisation au niveau global du module
        $this->denyAccessUnlessGranted(UserVoter::VIEW);

        /**
         * @var User
         */
        $currentUser = $this->getUser();

        // 2. Filtre de portée : Le siège voit tout, l'agent de succursale est limité à sa succursale [MCD 4]
        if ($this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_AGENCY_ADMIN')) {
            $users = $em->getRepository(User::class)->findBy([
                'agency' => $currentUser->getAgency(),
                'branch' => $currentUser->getBranch()
            ]);
        } else {
            $users = $em->getRepository(User::class)->findBy([
                'agency' => $currentUser->getAgency()
            ]);
        }

        return $this->render('admin/agent/index.html.twig', [
            'page' => 'agent',
            'users' => $users
        ]);
    }

    #[Route('/admin/agency/agent/add', name: 'admin_agency_agent_add')]
    #[Route('/admin/agency/agent/{code}/edit', name: 'admin_agency_agent_edit')]
    public function add_and_edit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ?string $code = null
    ): Response {

        /** @var User */
        $currentUser = $this->getUser();

        $isEdit = $code !== null;
        $agentEntity = $isEdit ? $em->getRepository(User::class)->findOneBy(['code' => $code]) : new User();

        if ($isEdit && !$agentEntity) {
            throw $this->createNotFoundException("Agent introuvable.");
        }

        $agentData = new AgentData();
        if ($isEdit) {
            $this->denyAccessUnlessGranted(UserVoter::WRITE, $agentEntity);

            $agentData->firstname = $agentEntity->getFirstname();
            $agentData->lastname = $agentEntity->getLastname();
            $agentData->email = $agentEntity->getEmail();
            $agentData->phone = $agentEntity->getPhone();
            $agentData->branchCode = $agentEntity->getBranch()?->getCode();
            $agentData->isActive = $agentEntity->isActive() ? '1' : '0';
        }

        $branches = $em->getRepository(Branch::class)->findBy(['agency' => $currentUser->getAgency()]);

        // Récupération dynamique des rôles Agence depuis la BDD
        $allAgencyRoles = $em->getRepository(Role::class)->findBy([
            'scope' => 'agency',
            'isActive' => true
        ]);

        // Filtrage hiérarchique strict
        $availableRoles = [];
        foreach ($allAgencyRoles as $role) {
            // RÈGLE : 'ROLE_AGENCY_ADMIN' n'apparaît JAMAIS dans le formulaire
            if ($role->getCode() === 'ROLE_AGENCY_ADMIN') {
                continue;
            }

            // Un gestionnaire simple ne peut pas attribuer le rôle de Direction Générale
            if (!$this->isGranted('ROLE_AGENCY_ADMIN')) {
                if ($role->getCode() === 'ROLE_GENERAL_MANAGER') {
                    continue;
                }
            }
            $availableRoles[] = $role;
        }

        $form = $this->createForm(AgentType::class, $agentData, [
            'is_edit' => $isEdit,
            'branches' => $branches,
            'roles' => $availableRoles,
            'with_branch' => true,
            'can_choose_general' => $this->isGranted('ROLE_AGENCY_ADMIN'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(UserVoter::WRITE, $agentData);

            $agentEntity->setFirstname($agentData->firstname);
            $agentEntity->setLastname($agentData->lastname);
            $agentEntity->setEmail($agentData->email);
            $agentEntity->setPhone($agentData->phone);
            $agentEntity->setUserType(UserType::Agency);
            $agentEntity->setAgency($currentUser->getAgency());

            if ($agentData->branchCode) {
                $branch = $em->getRepository(Branch::class)->findOneBy(['code' => $agentData->branchCode]);
                $agentEntity->setBranch($branch);
            } else {
                $agentEntity->setBranch(null);
            }

            if (!$isEdit) {
                $hashedPassword = $passwordHasher->hashPassword($agentEntity, $agentData->password);
                $agentEntity->setPassword($hashedPassword);
                $agentEntity->setCode('agt-' . random_int(1000, 9999));
            }

            if ($this->isGranted('ROLE_AGENCY_ADMIN')) {
                $agentEntity->setIsActive($agentData->isActive === '1');
            } else {
                if (!$isEdit) {
                    $agentEntity->setIsActive(false);
                }
            }

            $em->persist($agentEntity);
            $em->flush();

            $this->addFlash('success', 'Persistance de l\'agent effectuée avec succès.');
            return $this->redirectToRoute('admin_agency_agent_index');
        }

        return $this->render('admin/agent/form.html.twig', [
            'page' => 'agent',
            'form' => $form->createView(),
            'isEdit' => $isEdit,
            'agent' => $agentEntity
        ]);
    }




    #[Route('/admin/agency/agent/{code}/details', name: 'admin_agency_agent_show')]
    public function show(string $code, EntityManagerInterface $em): Response
    {
        // 1. Récupération de l'agent réel depuis la base de données
        $agentEntity = $em->getRepository(User::class)->findOneBy(['code' => $code]);

        if (!$agentEntity) {
            throw $this->createNotFoundException("L'agent demandé est introuvable.");
        }

        // 2. Contrôle de sécurité via votre UserVoter (Portée Agence & Succursale)
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $agentEntity);

        // 3. Rendu du template dans le bon répertoire avec l'entité réelle
        return $this->render('admin/agent/show.html.twig', [
            'page' => 'agent',
            'agent' => $agentEntity
        ]);
    }
}
