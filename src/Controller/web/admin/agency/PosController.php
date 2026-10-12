<?php
// src/Controller/Guichet/GuichetPosController.php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PosController extends AbstractController
{
    /**
     * ACCUEIL / CONSOLE TACTILE DU GUICHET DE GARE (SCOPE POS)
     */
    #[Route('/admin/agency/pos/accueil', name: 'agency_pos_index', methods: ['GET'])]
    public function index(): Response
    {
        // 1. Gare/Succursale physique d'exploitation courante [MCD 41]
        $currentBranch = ['id' => 1, 'name' => 'Gare Centrale (Gombe - Kinshasa)'];

        // 2. Devise Pivot et barèmes unitaires configurés par la compagnie [MCD 15]
        $agencyCurrencySettings = [
            'base_currency' => 'USD',
            'accepted_currencies' => [
                ['code' => 'CDF', 'symbol' => 'FC', 'exchange_rate' => 2850.00, 'isActive' => true],
                ['code' => 'XAF', 'symbol' => 'FCFA', 'exchange_rate' => 615.50, 'isActive' => true]
            ]
        ];

        // 3. État du tiroir-caisse du guichetier de service [MCD 43]
        $sessionVault = [
            'amount' => 482.00, // Volume des encaissements en espèces accumulé
            'openedAt' => new \DateTime('today 07:30:00')
        ];

        // 4. Grille des prochains bus imminents à remplir en gare
        $upcomingTrips = [
            [
                'id' => 42,
                'time' => '14h30',
                'route' => 'Kinshasa ➔ Kikwit via Kenge',
                'bus' => 'N°01 (Mercedes Marcopolo)',
                'plate' => '2432AB01',
                'seats_taken' => 41,
                'capacity' => 54,
                'status' => 'embarking' // Statut embarquement actif
            ],
            [
                'id' => 44,
                'time' => '16h00',
                'route' => 'Kinshasa ➔ Matadi',
                'bus' => 'N°12 (Scania Cargo)',
                'plate' => '8591AA01',
                'seats_taken' => 12,
                'capacity' => 50,
                'status' => 'planned'
            ]
        ];

        return $this->render('admin/agency/pos/index.html.twig', [
            'page' => 'pos',
            'current_branch' => $currentBranch,
            'agency_currency_settings' => $agencyCurrencySettings,
            'session_vault' => $sessionVault,
            'upcoming_trips' => $upcomingTrips
        ]);
    }
}
