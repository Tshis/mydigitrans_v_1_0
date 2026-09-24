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
        // src/Controller/Admin/Platform/PlatformDashboardController.php

        // ... (Dans ta méthode index())

        // CIRCONCRIPTION STRICTE : Uniquement les flux d'abonnements, créations d'agences et expirations de licences
        $recentActivities = [
            [
                'company_name' => 'TransKin Express (RDC)',
                'event_type'   => 'Création',
                'details'      => 'Ouverture du compte et validation juridique de la nouvelle agence sur la plateforme',
                'created_at'   => new \DateTime('2026-09-24 08:12:00'), // Aujourd'hui matin
                'revenue'      => 0.00
            ],
            [
                'company_name' => 'Océan du Gabon (Gabon)',
                'event_type'   => 'Abonnement',
                'details'      => 'Paiement de la licence mensuelle récurrente - Formule Premium (Multi-Tenant)',
                'created_at'   => new \DateTime('2026-09-23 14:45:00'), // Hier après-midi
                'revenue'      => 250.00
            ],
            [
                'company_name' => 'TransFleuve (Congo-B)',
                'event_type'   => 'Expiration',
                'details'      => 'Abonnement mensuel arrivé à échéance sans renouvellement. Compte basculé automatiquement en restriction d\'accès',
                'created_at'   => new \DateTime('2026-09-23 00:01:00'), // Hier minuit
                'revenue'      => 0.00
            ],
            [
                'company_name' => 'Congolaise des Voies (Congo-B)',
                'event_type'   => 'Abonnement',
                'details'      => 'Achat d\'une extension de licence pour l\'activation de 5 guichets de gare supplémentaires',
                'created_at'   => new \DateTime('2026-09-22 10:30:00'),
                'revenue'      => 125.00
            ],
            [
                'company_name' => 'Kivu Horizons (RDC)',
                'event_type'   => 'Création',
                'details'      => 'Enregistrement de la compagnie sur le réseau (Période d\'essai technique de 14 jours activée)',
                'created_at'   => new \DateTime('2026-09-21 16:20:00'),
                'revenue'      => 0.00
            ]
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
