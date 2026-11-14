<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\BusData;
use App\Entity\Bus;
use App\Entity\BusLayout;
use App\Enum\BusStatus;
use App\Enum\Scope;
use App\Form\agency\BusType;
use App\Service\FleetService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BusController extends AbstractAdminController
{
    public function __construct(
        private readonly FleetService $fleetService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/bus/list', name: 'admin_agency_bus_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('bus.view');
        $agency = $this->actor()->getAgency();

        return $this->render('admin/agency/bus/index.html.twig', [
            'page' => 'bus',
        ] + $this->fleetService->listAgencyFleet($agency));
    }

    #[Route('/admin/agency/bus/add', name: 'admin_agency_bus_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();

        $dto = new BusData();
        $form = $this->createForm(BusType::class, $dto, [
            'is_edit' => false,
            'layouts' => $this->fleetService->availableFactoryLayouts(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $bus = $this->fleetService->createBus($actor, $dto);

                $this->addFlash('success', sprintf(
                    'Le véhicule %s %s (%s) a été enregistré avec %d places configurées.',
                    $bus->getBrand(),
                    $bus->getModel(),
                    $bus->getPlateNumber(),
                    $bus->getSeats()->count()
                ));

                return $this->redirectToRoute('admin_agency_bus_show', ['code' => $bus->getCode()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/bus/add.html.twig', [
            'page' => 'bus',
            'form' => $form,
        ]);
    }

    #[Route('/admin/agency/bus/preview-layout/{id}', name: 'admin_agency_bus_preview_layout', methods: ['GET'])]
    public function seatmapRender(int $id): Response
    {
        $this->denyAccessUnlessGranted('bus.view');
        $this->actor();

        $layout = $this->fleetService->findValidatedFactoryLayout($id);
        if (!$layout instanceof BusLayout) {
            throw $this->createNotFoundException('Gabarit introuvable.');
        }

        return $this->render('system/seatmap.html.twig', [
            'seatmap' => $this->fleetService->buildSeatmap($layout),
        ]);
    }

    #[Route('/admin/agency/bus/{code}/details', name: 'admin_agency_bus_show', methods: ['GET', 'POST'])]
    public function show(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.view');
        $bus = $this->requireAgencyBus($code);

        $isEditable = $this->isGranted('bus.write') && BusStatus::Inactive !== $bus->getStatus();

        $dto = $this->fleetService->createDtoFromBus($bus);
        $form = $this->createForm(BusType::class, $dto, [
            'is_edit'     => true,
            'min_mileage' => $bus->getMileage(),
            'disabled'    => !$isEditable,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('bus.write');

            try {
                $this->fleetService->updateBus($bus, $dto);
                $this->addFlash('success', 'Les informations techniques du véhicule ont été mises à jour.');

                return $this->redirectToRoute('admin_agency_bus_show', ['code' => $bus->getCode()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/bus/show.html.twig', [
            'page' => 'bus',
            'form' => $form,
        ] + $this->fleetService->getBusProfile($bus));
    }

    #[Route('/admin/agency/bus/{code}/decommission', name: 'admin_agency_bus_decommission', methods: ['POST'])]
    public function decommission(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $bus = $this->requireAgencyBus($code);

        if (!$this->isCsrfTokenValid('bus_decommission_' . $bus->getCode(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $this->fleetService->decommissionBus($bus, (string) $request->request->get('inactive_reason'));
            $this->addFlash('success', sprintf('Le véhicule %s a été déclassé définitivement.', $bus->getPlateNumber()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_bus_show', ['code' => $bus->getCode()]);
    }

    #[Route('/admin/agency/bus/{code}/report-fault', name: 'admin_agency_bus_report_fault', methods: ['POST'])]
    public function reportFault(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();
        $bus = $this->requireAgencyBus($code);

        if (!$this->isCsrfTokenValid('bus_fault_' . $bus->getCode(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $this->fleetService->reportFault(
                $bus,
                $actor,
                (string) $request->request->get('issue'),
                (string) $request->request->get('severity', 'medium')
            );
            $this->addFlash('success', 'Panne enregistrée. Le véhicule est passé au statut Au Garage.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_bus_show', ['code' => $bus->getCode()]);
    }

    #[Route('/admin/agency/bus/{code}/resolve-fault', name: 'admin_agency_bus_resolve_fault', methods: ['POST'])]
    public function resolveFault(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();
        $bus = $this->requireAgencyBus($code);

        if (!$this->isCsrfTokenValid('bus_resolve_' . $bus->getCode(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $this->fleetService->resolveFault($bus, $actor, (string) $request->request->get('resolution'));
            $this->addFlash('success', 'Réparation validée. Le véhicule est de nouveau en service.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_bus_show', ['code' => $bus->getCode()]);
    }

    private function requireAgencyBus(string $code): Bus
    {
        $bus = $this->fleetService->findBusByCode($this->actor()->getAgency(), $code);
        if (!$bus instanceof Bus) {
            throw $this->createNotFoundException('Véhicule introuvable dans votre agence.');
        }

        return $bus;
    }
}
