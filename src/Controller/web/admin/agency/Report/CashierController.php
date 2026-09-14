<?php

// src/Controller/Admin/Agency/FinancialReportController.php
namespace App\Controller\Admin\Agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CashierController extends AbstractController
{


    #[Route('/admin/agency/report/cashiers', name: 'admin_agency_report_cashier_index')]
    public function index(): Response
    {
        // Matrice d'audit pour le contrôle des fiches de caisse par agent
        $cashierMatrix = [
            ['cashierName' => 'Antoinette Mputu', 'branchName' => 'Victoire', 'ticketsCount' => 48, 'total_cdf' => 2160000, 'total_usd' => 140.00, 'sessionStatus' => 'closed'],
            ['cashierName' => 'Jean Mukendi', 'branchName' => 'Matadi', 'ticketsCount' => 12, 'total_cdf' => 540000, 'total_usd' => 0.00, 'sessionStatus' => 'open']
        ];

        return $this->render('admin/agency/report/financial/cashier_report.html.twig', ['cashier_matrix' => $cashierMatrix]);
    } //index
}
