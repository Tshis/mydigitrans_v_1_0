<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\BranchData;
use App\Entity\Branch;
use App\Enum\Scope;
use App\Form\agency\BranchType;
use App\Service\BranchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BranchController extends AbstractAdminController
{
    public function __construct(
        private readonly BranchService $branchService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/branches', name: 'admin_agency_branch_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('branch.view');
        $agency = $this->actor()->getAgency();

        return $this->render('admin/agency/branch/index.html.twig', [
            'page' => 'branch',
        ] + $this->branchService->listAgencyBranches($agency));
    }

    #[Route('/admin/agency/branch/new', name: 'admin_agency_branch_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $this->denyAccessUnlessGranted('branch.write');
        $actor  = $this->actor();
        $agency = $actor->getAgency();

        $dto = new BranchData();
        $form = $this->createForm(BranchType::class, $dto, [
            'agents' => $this->branchService->getAgencyAgents($agency),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $branch = $this->branchService->createBranch($actor, $agency, $dto);
                $this->addFlash('success', sprintf('La succursale « %s » (%s) a été ouverte avec succès.', $branch->getName(), $branch->getCode()));

                return $this->redirectToRoute('admin_agency_branch_show', ['code' => $branch->getCode()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/branch/add.html.twig', [
            'page' => 'branch',
            'form' => $form,
        ]);
    }

    #[Route('/admin/agency/branch/modification/{code}', name: 'admin_agency_branch_edit', methods: ['GET', 'POST'])]
    public function edit(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('branch.write');
        $actor  = $this->actor();
        $branch = $this->requireAgencyBranch($code);

        $dto = $this->branchService->createDtoFromBranch($branch);
        $form = $this->createForm(BranchType::class, $dto, [
            'agents' => $this->branchService->getAgencyAgents($actor->getAgency()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->branchService->updateBranch($actor, $branch, $dto);
                $this->addFlash('success', sprintf('La succursale « %s » a été mise à jour.', $branch->getName()));

                return $this->redirectToRoute('admin_agency_branch_show', ['code' => $branch->getCode()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/branch/edit.html.twig', [
            'page'   => 'branch',
            'form'   => $form,
            'branch' => $branch,
        ]);
    }

    #[Route('/admin/agency/branch/{code}/details', name: 'admin_agency_branch_show', methods: ['GET', 'POST'])]
    public function show(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('branch.view');
        $branch = $this->requireAgencyBranch($code);

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted('branch.write');

            if (!$this->isCsrfTokenValid('branch_status_' . $branch->getCode(), (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Jeton CSRF invalide.');
            }

            $newStatus = (string) $request->request->get('branch_status', 'active');
            $this->branchService->updateStatus($branch, $newStatus);
            $this->addFlash('success', sprintf('Le statut de la succursale %s a été mis à jour.', $branch->getName()));

            return $this->redirectToRoute('admin_agency_branch_show', ['code' => $branch->getCode()]);
        }

        return $this->render('admin/agency/branch/show.html.twig', [
            'page' => 'branch',
        ] + $this->branchService->getBranchProfile($branch));
    }

    private function requireAgencyBranch(string $code): Branch
    {
        $branch = $this->branchService->findAgencyBranchByCode($this->actor()->getAgency(), $code);
        if (!$branch instanceof Branch) {
            throw $this->createNotFoundException('Succursale introuvable dans votre agence.');
        }

        return $branch;
    }
}
