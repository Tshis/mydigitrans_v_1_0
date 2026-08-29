<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MaintenanceController extends AbstractController
{
    #[Route('/admin/agency/maintenance/list', name: 'admin_agency_maintenance_index')]
    public function index(): Response
    {

        // Données simulées de l'historique de l'Atelier Central
        // On respecte tes deux états : 'resolved' et 'broken'
        $fleetLogs = [
            [
                'brand' => 'Mercedes-Benz',
                'model' => 'Sprinter 316',
                'plateNumber' => 'A-1234-BC',
                'reportedAt' => new \DateTime('2026-08-20'),
                'issue' => 'Surchauffe moteur sur la route de Kenge',
                'solution' => 'Remplacement du joint de culasse et purge du radiateur',
                'resolvedAt' => new \DateTime('2026-08-24'),
                'status' => 'resolved',
                'statusLabel' => 'Résolu',
                'code' => 'bus-001'
            ],
            [
                'brand' => 'Toyota',
                'model' => 'Coaster',
                'plateNumber' => 'A-5678-DE',
                'reportedAt' => new \DateTime('2026-08-28'),
                'issue' => 'Réchauffement du radiateur et fuite de liquide',
                'solution' => null, // Pas encore de solution
                'resolvedAt' => null,
                'status' => 'broken',
                'statusLabel' => 'Panne',
                'code' => 'bus-002'
            ],
            [
                'brand' => 'Scania',
                'model' => 'K410',
                'plateNumber' => 'A-9012-FG',
                'reportedAt' => new \DateTime('2026-08-15'),
                'issue' => 'Amortisseurs arrières usés sur la RN1',
                'solution' => 'Changement complet des kits de suspension arrière',
                'resolvedAt' => new \DateTime('2026-08-18'),
                'status' => 'resolved',
                'statusLabel' => 'Résolu',
                'code' => 'bus-003'
            ]
        ];

        return $this->render('admin/agency/maintenance/index.html.twig', [
            'page' => 'maintenance',
            'fleet' => $fleetLogs // Envoi du tableau au Twig
        ]);
    } //index

    #[Route('/admin/agency/maintenance/add', name: 'admin_agency_maintenance_add')]
    public function add(): Response
    {

        $fleet = [
            [
                'brand' => 'Mercedes-Benz',
                'model' => 'Sprinter 316',
                'type' => 'Minimaintenance',
                'plateNumber' => 'A-1234-BC',
                'capacity' => 19,
                'mileage' => 142050,
                'docStatus' => 'up-to-date',
                'status' => 'available',
                'statusLabel' => 'Disponible'
            ],
            [
                'brand' => 'Toyota',
                'model' => 'Coaster',
                'type' => 'Bus Interurbain',
                'plateNumber' => 'A-5678-DE',
                'capacity' => 30,
                'mileage' => 89400,
                'docStatus' => 'up-to-date',
                'status' => 'on_road',
                'statusLabel' => 'En Voyage'
            ],
            [
                'brand' => 'Scania',
                'model' => 'K410',
                'type' => 'Autocar Grand Confort',
                'plateNumber' => 'A-9012-FG',
                'capacity' => 54,
                'mileage' => 310200,
                'docStatus' => 'expired',
                'status' => 'broken',
                'statusLabel' => 'En Panne'
            ]
        ];




        return $this->render('admin/agency/maintenance/add.html.twig', [
            'page' => 'maintenance',
            'fleet' => $fleet
        ]);
    } //add
}
