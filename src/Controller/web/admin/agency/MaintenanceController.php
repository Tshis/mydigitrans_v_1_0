<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\BusMaintenanceDeclareData;
use App\Dto\admin\agency\BusMaintenanceResolveData;
use App\Entity\BusMaintenance;
use App\Enum\Scope;
use App\Form\admin\agency\BusMaintenanceDeclareType;
use App\Form\admin\agency\BusMaintenanceResolveType;
use App\Service\BusMaintenanceService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MaintenanceController extends AbstractAdminController
{
    public function __construct(
        private readonly BusMaintenanceService $maintenanceService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/maintenance/list', name: 'admin_agency_maintenance_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('bus.view');
        $agency = $this->actor()->getAgency();

        return $this->render('admin/agency/maintenance/index.html.twig', [
            'page' => 'maintenance',
        ] + $this->maintenanceService->listAgencyMaintenances($agency));
    }

    #[Route('/admin/agency/maintenance/declare', name: 'admin_agency_maintenance_declare', methods: ['GET', 'POST'])]
    public function declare(Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();

        $dto = new BusMaintenanceDeclareData();
        $form = $this->createForm(BusMaintenanceDeclareType::class, $dto, [
            'active_buses' => $this->maintenanceService->operationalBuses($actor->getAgency()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $maintenance = $this->maintenanceService->declareFault($actor, $dto);

                $this->addFlash('success', sprintf(
                    'Panne enregistrée avec succès pour le véhicule %s (%s). Statut basculé au garage.',
                    $maintenance->getBus()?->getPlateNumber(),
                    $maintenance->getBus()?->getCode()
                ));

                return $this->redirectToRoute('admin_agency_maintenance_index');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/maintenance/declare.html.twig', [
            'page' => 'maintenance',
            'form' => $form,
        ]);
    }

    #[Route('/admin/agency/maintenance/{id}/resolve', name: 'admin_agency_maintenance_resolve', methods: ['GET', 'POST'])]
    public function resolve(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();
        $maintenance = $this->requireAgencyMaintenance($id);

        $dto = new BusMaintenanceResolveData();
        $form = $this->createForm(BusMaintenanceResolveType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->maintenanceService->resolveFault($maintenance, $actor, $dto);

                $this->addFlash('success', sprintf(
                    'La panne du bus %s a été résolue. Le véhicule est de nouveau en service.',
                    $maintenance->getBus()?->getPlateNumber()
                ));

                return $this->redirectToRoute('admin_agency_maintenance_index');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/maintenance/resolve.html.twig', [
            'page' => 'maintenance',
            'form' => $form,
            'bus'  => $maintenance->getBus(),
            'log'  => $maintenance,
        ]);
    }

    #[Route('/admin/agency/maintenance/{id}/cancel', name: 'admin_agency_maintenance_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();
        $maintenance = $this->requireAgencyMaintenance($id);

        if (!$this->isCsrfTokenValid('maintenance_cancel_' . $maintenance->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $this->maintenanceService->cancelFault($maintenance, $actor);
            $this->addFlash('success', 'Le ticket de maintenance a été annulé et le bus remis en service.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_maintenance_index');
    }

    private function requireAgencyMaintenance(int $id): BusMaintenance
    {
        $maintenance = $this->maintenanceService->findAgencyMaintenance($this->actor()->getAgency(), $id);
        if (!$maintenance instanceof BusMaintenance) {
            throw $this->createNotFoundException('Ticket de maintenance introuvable dans votre agence.');
        }

        return $maintenance;
    }
}
