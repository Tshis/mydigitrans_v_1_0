<?php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController extends AbstractController
{

    /**
     * REPORT : CONSOLIDATION ANALYTIQUE DES REVENUS ET CHARGES DU SAAS [MCD 34/44/45]
     */
    // src/Controller/Admin/Platform/PlatformReportController.php

    #[Route('/admin/platform/reports/finance', name: 'admin_platform_report_finance', methods: ['GET'])]
    public function financeReport(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        // Interception dynamique des filtres de dates (par défaut, le mois de septembre 2026 complet)
        $startDate = $request->query->get('start_date') ? new \DateTime($request->query->get('start_date')) : new \DateTime('2026-09-01');
        $endDate = $request->query->get('end_date') ? new \DateTime($request->query->get('end_date')) : new \DateTime('2026-09-30');

        // Synthèse globale sur cette période spécifique
        $summary = [
            'total_subscriptions' => 3125.00,
            'total_fees' => 1725.00,
            'total_expenses' => 1240.00
        ];

        // Compilation du cours chronologique jour après jour (Simulation BDD platformFinancialOperation) [MCD 45]
        $timelineRecords = [
            ['date' => new \DateTime('2026-09-01'), 'subscriptions' => 500.00, 'fees' => 250.00, 'expenses' => 0.00, 'net' => 750.00],
            ['date' => new \DateTime('2026-09-07'), 'subscriptions' => 250.00, 'fees' => 310.00, 'expenses' => 110.00, 'net' => 450.00],
            ['date' => new \DateTime('2026-09-14'), 'subscriptions' => 1250.00, 'fees' => 280.00, 'expenses' => 880.00, 'net' => 650.00],
            ['date' => new \DateTime('2026-09-21'), 'subscriptions' => 750.00, 'fees' => 440.00, 'expenses' => 0.00, 'net' => 1190.00],
            ['date' => new \DateTime('2026-09-28'), 'subscriptions' => 375.00, 'fees' => 445.00, 'expenses' => 250.00, 'net' => 570.00],
        ];

        return $this->render('admin/platform/report/finance.html.twig', [
            'page' => 'report',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary' => $summary,
            'timeline_records' => $timelineRecords
        ]);
    }
}
