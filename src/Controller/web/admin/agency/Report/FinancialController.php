<?php

namespace App\Controller\web\admin\agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FinancialController extends AbstractController
{
    #[Route('/admin/agency/report/financial/home', name: 'admin_agency_report_financial_indexxx')]
    public function indexxx(Request $request): Response
    {

        // Intercepte les filtres ou applique des valeurs par défaut pour le mois en cours
        $dateFrom = $request->query->get('date_from', '2026-08-01');
        $dateTo = $request->query->get('date_to', '2026-08-31');
        $currentBranch = $request->query->get('branch_code', '');

        // Liste des succursales pour ton sélecteur
        $branchesList = [
            ['code' => 'SUC-KIN-01', 'name' => 'Victoire - Rond Point'],
            ['code' => 'SUC-MAT-02', 'name' => 'Matadi Ville - Port']
        ];

        // Simulation de la matrice d'écritures comptables issues de ton MCD
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

        // return $this->render('admin/agency/report/financial/index.html.twig', [
        return $this->render('admin/agency/caiss/index.html.twig', [
            'page' => 'report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'current_branch' => $currentBranch,
            'branches_list' => $branchesList,
            'report_matrix' => $reportMatrix
        ]);
    } //index


    #[Route('/admin/agency/reports/financial', name: 'admin_agency_report_financial_index')]
    public function index(Request $request): Response
    {
        $dateFrom = $request->query->get('date_from', '2026-08-01');
        $dateTo = $request->query->get('date_to', '2026-08-31');
        $currentBranch = $request->query->get('branch_code', '');

        $branchesList = [
            ['code' => 'SUC-KIN-01', 'name' => 'Victoire - Rond Point'],
            ['code' => 'SUC-MAT-02', 'name' => 'Matadi Ville - Port']
        ];

        $reportMatrix = [
            ['date' => new \DateTime('2026-08-28'), 'branchCode' => 'SUC-KIN-01', 'branchName' => 'Victoire', 'ticketRevenue' => 1450000, 'cargoRevenue' => 450000, 'currency' => 'CDF', 'auditStatus' => 'verified', 'auditStatusLabel' => 'Validé'],
            ['date' => new \DateTime('2026-08-28'), 'branchCode' => 'SUC-MAT-02', 'branchName' => 'Matadi Ville', 'ticketRevenue' => 980000, 'cargoRevenue' => 120000, 'currency' => 'CDF', 'auditStatus' => 'verified', 'auditStatusLabel' => 'Validé'],
        ];

        return $this->render('admin/agency/report/financial/index.html.twig', [
            'page' => 'report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'current_branch' => $currentBranch,
            'branches_list' => $branchesList,
            'report_matrix' => $reportMatrix
        ]);
    } //index

    #[Route('/admin/agency/reports/financial/{date}/{branch_code}/details', name: 'admin_agency_report_financial_show')]
    public function show(string $date, string $branch_code): Response
    {
        // Simulation des transactions détaillées de la journée sélectionnée
        $transactions = [
            ['time' => '08:14', 'reference' => 'TX-TK-8842', 'type' => 'ticket', 'label' => 'Mukendi Jean', 'details' => 'Billet Kinshasa -> Matadi, Siège 14', 'cashier' => 'Antoinette Mputu', 'amount' => 45000, 'currency' => 'CDF'],
            ['time' => '09:30', 'reference' => 'TX-CG-1102', 'type' => 'cargo', 'label' => 'Mbuyi Thérèse', 'details' => 'Expédition 2 Sacs de braises (75 Kg)', 'cashier' => 'Antoinette Mputu', 'amount' => 35000, 'currency' => 'CDF']
        ];

        return $this->render('admin/agency/report/financial/show.html.twig', [
            'page' => 'report',
            'selected_date' => new \DateTime($date),
            'current_branch' => $branch_code,
            'transactions' => $transactions
        ]);
    }
}
