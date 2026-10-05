<?php
// src/Controller/Partner/PartnerDashboardController.php

namespace App\Controller\web\admin\partner;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    /**
     * ACUEIL / DASHBOARD DE L'ESPACE APPORTEUR DE LIER SANS COUTURE [MCD 47]
     */
    #[Route('/admin/partner/dashboard', name: 'admin_partner_dashboard_index', methods: ['GET'])]
    public function index(): Response
    {
        // 1. Définition du token unique de l'apporteur connecté (Simulé via app.user.referral_token)
        $partnerToken = 'MOMBONGO85';

        // 2. Compilation de ses compteurs de gains et réseau
        $stats = [
            'pending_balance' => 142.50, // Prêt à être décaissé (approved)
            'total_earned' => 450.00,    // Somme cumulée historique à vie
            'filleuls_count' => 4        // Agences ou sous-agents parrainés
        ];

        // 3. Extraction de ses commissions récentes filtrées (Table 47)
        $recentCommissions = [
            [
                'id' => 8801,
                'agency_name' => 'TransKin Express',
                'invoice_number' => 'INV-2026-0092',
                'level' => 1,
                'percentage' => 10.0,
                'amount' => 25.00,
                'createdAt' => new \DateTime('now'),
                'status' => 'approved'
            ],
            [
                'id' => 8802,
                'agency_name' => 'Océan du Gabon',
                'invoice_number' => 'INV-2026-0081',
                'level' => 2,
                'percentage' => 5.0,
                'amount' => 12.50,
                'createdAt' => new \DateTime('-1 day'),
                'status' => 'paid'
            ],
            [
                'id' => 8803,
                'agency_name' => 'TransFleuve',
                'invoice_number' => 'INV-2026-0040',
                'level' => 1,
                'percentage' => 10.0,
                'amount' => 7.50,
                'createdAt' => new \DateTime('-6 days'),
                'status' => 'paid' // En carence (-7j)
            ]
        ];

        return $this->render('admin/partner/dashboard.html.twig', [
            'page' => 'dashboard',
            'partner_token' => $partnerToken,
            'stats' => $stats,
            'recent_commissions' => $recentCommissions
        ]);
    }//index

    /**
     * MATRICE : SUIVI ET CARTOGRAPHIE COMPLÈTE DU RÉSEAU DIRECT ET INDIRECT
     */
    #[Route('/admin/partner/my-network', name: 'admin_partner_network_index', methods: ['GET'])]
    public function myNetwork(): Response
    {
        $affiliatesList = [
            ['id' => 101, 'name' => 'TransKin Express', 'type' => 'agency', 'level' => 1, 'recruited_by' => null, 'createdAt' => new \DateTime('-2 months'), 'license_status' => 'active', 'total_contributed' => 50.00],
            ['id' => 11, 'name' => 'Blaise Mvumbi', 'type' => 'partner', 'level' => 1, 'recruited_by' => null, 'createdAt' => new \DateTime('-1 month'), 'isActive' => true, 'license_status' => 'active', 'total_contributed' => 0.00],
            ['id' => 102, 'name' => 'Océan du Gabon', 'type' => 'agency', 'level' => 2, 'recruited_by' => 'Blaise Mvumbi', 'createdAt' => new \DateTime('-2 weeks'), 'license_status' => 'active', 'total_contributed' => 12.50],
            ['id' => 103, 'name' => 'TransFleuve', 'type' => 'agency', 'level' => 1, 'recruited_by' => null, 'createdAt' => new \DateTime('-5 days'), 'license_status' => 'expired', 'total_contributed' => 0.00],
        ];

        return $this->render('admin/partner/network.html.twig', [
            'page' => 'network',
            'affiliates_list' => $affiliatesList
        ]);
    }//myNetwork

    /**
     * WITHDRAWALS : CONSULTATION DU GRAND LIVRE DES PAIEMENTS RETIRÉS [MCD 48]
     */
    #[Route('/admin/partner/my-withdrawals', name: 'admin_partner_withdrawal_index', methods: ['GET'])]
    public function myWithdrawals(): Response
    {
        $paymentsHistory = [
            ['id' => 401, 'reference' => 'REM-MOBILE-20261001-A4F2', 'method' => 'Mobile Money (M-Pesa)', 'amount' => 142.50, 'currency' => 'USD', 'paidAt' => new \DateTime('-4 days'), 'status' => 'success'],
            ['id' => 402, 'reference' => 'REM-CASH-20260915-F8B9', 'method' => 'Espèces / Caisse Gombe', 'amount' => 38.00, 'currency' => 'USD', 'paidAt' => new \DateTime('-3 weeks'), 'status' => 'success']
        ];

        return $this->render('admin/partner/withdrawal.html.twig', [
            'page' => 'withdrawal',
            'payments_history' => $paymentsHistory
        ]);
    } //myWithdrawals

}
