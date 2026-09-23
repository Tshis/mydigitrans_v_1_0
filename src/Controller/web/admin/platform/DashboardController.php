<?php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController extends AbstractController
{

    #[Route('/admin/platform/dashboard', name: 'admin_platform_dashboard')]
    public function dashboard(): Response
    {

        // 1. Statistiques macro réorientées uniquement sur la santé opérationnelle du SaaS
        $stats = [
            'total_companies'    => 12,
            'active_companies'   => 10, // Compagnies avec abonnements à jour
            'inactive_companies' => 2,  // Licences expirées / bloquées
            'alert_companies' => 3,  // Licences en cours d'expiration
            'tickets_today'      => 1420, // Volumétrie des billets vendus ce jour sur tout le réseau international
            'partners'      => 15 // Volumétrie des billets vendus ce jour sur tout le réseau international
        ];



        // 2. Flux d'activité ciblé sur les actions inter-agences vis-à-vis du SaaS
        $recentActivities = [
            [
                'company_name' => 'TransKin Express (RDC)',
                'event_type'   => 'Abonnement',
                'details'      => 'Renouvellement de la licence mensuelle - Formule Premium Multi-Tenant',
                'created_at'   => new \DateTime('now'),
                'revenue'      => 250.00
            ],
            [
                'company_name' => 'Ocean du Gabon (Gabon)',
                'event_type'   => 'Promotion',
                'details'      => 'Application du code réduction "MOMBONGO20" sur l\'extension du module Fret',
                'created_at'   => new \DateTime('-2 hours'),
                'revenue'      => 0.00
            ],
            [
                'company_name' => 'Congolaise des Voies (Congo-B)',
                'event_type'   => 'Abonnement',
                'details'      => 'Achat de licence pour 3 nouveaux guichets de gares additionnels',
                'created_at'   => new \DateTime('-1 day'),
                'revenue'      => 75.00
            ],
        ];

        // 3. Référentiel global de l'entité 15. Currency
        $systemCurrencies = [
            ['code' => 'CDF', 'name' => 'Franc Congolais'],
            ['code' => 'USD', 'name' => 'Dollar Américain'],
            ['code' => 'XAF', 'name' => 'Franc CFA (BEAC) '],
            ['code' => 'XOF', 'name' => 'Franc CFA (BCEAO)'],
            ['code' => 'EUR', 'name' => 'Euro'],
        ];




        return $this->render('admin/platform/dashboard/dashboard.html.twig', [
            'page'              => 'dashboard',
            'stats'             => $stats,
            'recent_activities' => $recentActivities,
            'system_currencies' => $systemCurrencies
        ]);
    } //dashboard
}
