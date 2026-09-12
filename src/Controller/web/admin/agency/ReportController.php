<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController extends   AbstractController
{


    #[Route('/admin/agency/reports', name: 'admin_agency_report_index')]
    public function index(Request $request): Response
    {
        // Récupération des filtres de la requête ou valeurs par défaut
        $dateFrom = $request->query->get('date_from', '2026-08-01');
        $dateTo = $request->query->get('date_to', '2026-08-31');
        $currentBranch = $request->query->get('branch_code', '');

        // Liste des succursales pour alimenter le sélecteur
        $branchesList = [
            ['code' => 'SUC-KIN-01', 'name' => 'Victoire - Rond Point'],
            ['code' => 'SUC-MAT-02', 'name' => 'Matadi Ville - Port']
        ];

        // Simulation des lignes financières consolidées du rapport
        $reportMatrix = [
            [
                'date' => new \DateTime('2026-08-28'),
                'branchCode' => 'SUC-KIN-01',
                'branchName' => 'Victoire - Rond Point',
                'ticketRevenue' => 1450000,
                'cargoRevenue' => 450000,
                'currency' => 'CDF',
                'auditStatus' => 'verified',
                'auditStatusLabel' => 'Validé'
            ],
            [
                'date' => new \DateTime('2026-08-28'),
                'branchCode' => 'SUC-MAT-02',
                'branchName' => 'Matadi Ville - Port',
                'ticketRevenue' => 980000,
                'cargoRevenue' => 120000,
                'currency' => 'CDF',
                'auditStatus' => 'verified',
                'auditStatusLabel' => 'Validé'
            ],
            [
                'date' => new \DateTime('2026-08-29'),
                'branchCode' => 'SUC-KIN-01',
                'branchName' => 'Victoire - Rond Point',
                'ticketRevenue' => 1890000,
                'cargoRevenue' => 610000,
                'currency' => 'CDF',
                'auditStatus' => 'pending',
                'auditStatusLabel' => 'En attente'
            ]
        ];

        return $this->render('admin/agency/caisse/index.html.twig', [
            'page' => 'report',
            'total_revenue' => 5500000,
            'total_tickets' => 184,
            'total_cargo_consignments' => 42,
            'currency' => 'CDF',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'current_branch' => $currentBranch,
            'branches_list' => $branchesList,
            'report_matrix' => $reportMatrix
        ]);
    }
}
