<?php
// src/Controller/Admin/Platform/AgencyController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AgencyController extends AbstractController
{
    /**
     * INDEX : LISTE DES AGENCES ET ETAT DES LICENCES SAAS [MCD 16]
     */
    #[Route('/admin/platform/agencies', name: 'admin_platform_agency_index')]
    public function index(): Response
    {
        // Simulation des agences de transport partenaires (Multi-pays / Multi-devises)
        $agenciesList = [
            [
                'id' => 1,
                'name' => 'TransKin Express',
                'country' => 'RD Congo',
                'base_currency' => 'CDF',
                'plan_name' => 'Premium Multitenant',
                'expired_at' => new \DateTime('2027-01-15'),
                'status' => 'Actif'
            ],
            [
                'id' => 2,
                'name' => 'Océan du Gabon',
                'country' => 'Gabon',
                'base_currency' => 'XAF',
                'plan_name' => 'Premium Multitenant',
                'expired_at' => new \DateTime('2026-12-30'),
                'status' => 'Actif'
            ],
            [
                'id' => 3,
                'name' => 'TransFleuve',
                'country' => 'Congo-Brazzaville',
                'base_currency' => 'XAF',
                'plan_name' => 'Basique Standard',
                'expired_at' => new \DateTime('2026-09-01'),
                'status' => 'Expiré' // Bloqué par automatisme
            ],
            [
                'id' => 4,
                'name' => 'Kivu Horizons',
                'country' => 'RD Congo',
                'base_currency' => 'USD',
                'plan_name' => 'Premium Multitenant',
                'expired_at' => new \DateTime('2026-10-15'),
                'status' => 'Suspendu' // Gelé manuellement
            ]
        ];

        return $this->render('admin/platform/agency/index.html.twig', [
            'page' => 'agency',
            'companies_list' => $agenciesList // Conserve la variable Twig active pour ton tableau
        ]);
    }
}
