<?php

namespace App\Controller\web\admin\agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FleetController extends AbstractController
{

    #[Route('/admin/agency/report/fleet/home', name: 'admin_agency_report_fleet_index')]
    public function index(): Response
    {
        $vehiclesMatrix = [
            ['brand' => 'Toyota', 'model' => 'Coaster', 'plateNumber' => 'A-5678-DE', 'grossRevenue' => 4850000, 'maintenanceCost' => 450000, 'currency' => 'CDF']
        ];
        return $this->render('admin/agency/report/fleet/index.html.twig', [
            'page' => 'report',
            'date_from' => '2026 - 08 - 31',
            'date_to' => '2026 - 09 - 14',
            'vehicles_matrix' => $vehiclesMatrix
        ]);
    } //index

    #[Route('/admin/agency/reports/fleet/{plate}/show', name: 'admin_agency_report_fleet_show')]
    public function show(string $plate): Response
    {
        $stats = ['occupancyRate' => 78.4, 'totalTrips' => 24, 'kmTraveled' => 8400];
        return $this->render('admin/agency/report/fleet/show.html.twig', [
            'page' => 'report',
            'plate_number' => $plate,
            'stats' => $stats
        ]);
    } //show
}
