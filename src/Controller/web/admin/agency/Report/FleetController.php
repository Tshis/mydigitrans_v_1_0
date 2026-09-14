<?php

// src/Controller/Admin/Agency/AdvancedReportController.php
namespace App\Controller\Admin\Agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FleetController extends AbstractController
{

    #[Route('/admin/agency/report/fleet/home', name: 'admin_agency_report_fleet_index')]
    public function fleetIndex(): Response
    {
        $vehiclesMatrix = [
            ['brand' => 'Toyota', 'model' => 'Coaster', 'plateNumber' => 'A-5678-DE', 'grossRevenue' => 4850000, 'maintenanceCost' => 450000, 'currency' => 'CDF']
        ];
        return $this->render('admin/agency/report/fleet/index.html.twig', ['vehicles_matrix' => $vehiclesMatrix]);
    }

    #[Route('/admin/agency/reports/fleet/{plate}/show', name: 'admin_agency_report_fleet_show')]
    public function fleetShow(string $plate): Response
    {
        $stats = ['occupancyRate' => 78.4, 'totalTrips' => 24, 'kmTraveled' => 8400];
        return $this->render('admin/agency/report/fleet/show.html.twig', ['plate_number' => $plate, 'stats' => $stats]);
    }
}
