<?php
// src/Controller/Admin/Platform/PlatformSubscriptionController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    /**
     * INDEX : RECAPITULATIF FINANCIER DES ABONNEMENTS SAAS ET LICENCES [MCD 16]
     */
    #[Route('/admin/platform/subscriptions', name: 'admin_platform_subscription_index', methods: ['GET'])]
    public function index(): Response
    {
        // 1. Indicateurs clés de croissance récurrente
        $stats = [
            'mrr' => 3125.00,
            'pending_expirations' => 1,
            'monthly_collected' => 2850.00
        ];

        // 2. Registre de facturation des agences clientes
        $subscriptionsList = [
            [
                'agency_name' => 'TransKin Express',
                'plan_name' => 'Premium Multitenant',
                'started_at' => new \DateTime('2025-01-15'),
                'expires_at' => new \DateTime('2027-01-15'),
                'price' => 250.00,
                'status' => 'Payé'
            ],
            [
                'agency_name' => 'Océan du Gabon',
                'plan_name' => 'Premium Multitenant',
                'started_at' => new \DateTime('2025-06-30'),
                'expires_at' => new \DateTime('2026-12-30'),
                'price' => 250.00,
                'status' => 'Payé'
            ],
            [
                'agency_name' => 'TransFleuve',
                'plan_name' => 'Basique Standard',
                'started_at' => new \DateTime('2025-09-01'),
                'expires_at' => new \DateTime('2026-09-01'),
                'price' => 75.00,
                'status' => 'Expiré'
            ]
        ];

        // 3. Référentiel d'usine des forfaits commerciaux du SaaS
        $platformPlans = [
            [
                'id' => 1,
                'name' => 'Basique Standard',
                'price' => 75.00,
                'level' => 'Starter',
                'features' => ['Flotte bridée à 5 bus maximum', '1 gare / succursale unique', 'Billetterie mono-devise', 'Rapports basiques en local']
            ],
            [
                'id' => 2,
                'name' => 'Premium Multitenant',
                'price' => 250.00,
                'level' => 'Recommandé',
                'features' => ['Flotte illimitée (Bus maillés)', 'Succursales / Branches illimitées', 'Trésorerie multi-devises (XAF, CDF, USD)', 'Support d\'exceptions d\'usines (WC, Chauffeur)', 'Sauvegarde cloud quotidienne']
            ],
            [
                'id' => 3,
                'name' => 'VIP Corporation',
                'price' => 490.00,
                'level' => 'Entreprise',
                'features' => ['Toutes les fonctions Premium', 'Accès complet aux API système', 'Module de fret & colisage avancé', 'Console d\'audits comptables poussée', 'Gestionnaire de compte MyDigitrans dédié']
            ]
        ];


        return $this->render('admin/platform/subscription/index.html.twig', [
            'page' => 'subscription',
            'stats' => $stats,
            'platform_plans' => $platformPlans, // Injection cruciale !
            'subscriptions_list' => $subscriptionsList
        ]);
    }
}
