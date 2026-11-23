<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Enum\Scope;
use App\Service\AgencyDashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractAdminController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/dashboard', name: 'admin_agency_dashboard', methods: ['GET'])]
    public function index(Request $request, AgencyDashboardService $dashboard): Response
    {
        if (
            !$this->isGranted('ROLE_AGENCY_ADMIN')
            && !$this->isGranted('ROLE_GENERAL_MANAGER')
            && !$this->isGranted('ROLE_MANAGER')
            && !$this->isGranted('ROLE_ACCOUNTANT')
        ) {
            if ($this->isGranted('cash.view')) {
                return $this->redirectToRoute('admin_agency_cashier_index');
            }
            if ($this->isGranted('reservation.view')) {
                return $this->redirectToRoute('admin_agency_reservation_index');
            }
            if ($this->isGranted('shipment.view')) {
                return $this->redirectToRoute('admin_agency_shipment_index');
            }

            throw $this->createAccessDeniedException('Accès réservé à la direction et aux responsables de succursale.');
        }

        $user = $this->actor();

        $view = $dashboard->build(
            $user,
            (string) $request->query->get('branch_code', ''),
            new \DateTimeImmutable()
        );

        return $this->render('admin/agency/dashboard/index.html.twig', [
            'page' => 'dashboard',
        ] + $view);
    }
}
