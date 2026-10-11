<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AgencyController extends AbstractController
{

    #[Route('/public/agency/create', name: 'public_agency_add')]
    public function add(): Response
    {
        return $this->render('public/agency/add1.html.twig', [
            'page' => 'agency',
        ]);
    } //add

    /**
     * ENRÔLEMENT : FORMULAIRE DOUBLE ROUTE DE CRÉATION D'AGENCE AVEC ACCROCHAGE SPONSOR [Profile, MNS]
     */
    #[Route('/register-agency', name: 'public_agency_register', methods: ['GET'])]
    #[Route('/register-agency/{referal_token}', name: 'public_agency_register_sponsored', methods: ['GET'])]
    public function showRegisterForm(?string $referal_token = null): Response
    {
        // Capture du jeton de parrainage s'il est présent dans l'URL (Ex: /register-agency/MDTDAN718) [MNS]
        $requestedSponsor = $referal_token;

        return $this->render('public/agency/register.html.twig', [
            'page' => 'agency',
            'requested_sponsor' => $requestedSponsor
        ]);
    }//showRegisterForm

    /**
     * PROCESSEUR ÉTAPE 1 : INTERCEPTION ET REDIRECTION VERS LE CATALOGUE SAAS [INDEX]
     */
    #[Route('/register-agency/process', name: 'public_agency_register_process', methods: ['POST'])]
    public function processRegister(Request $request): Response
    {
        // EN COULISSES DOCTRINE :
        // 1. Instanciation de la nouvelle "1. Agency" au statut d'attente
        // 2. Si sponsor_token est fourni, on pointe la table User pour lier l'agence à l'apporteur (parent_id) [Profile]
        // 3. Redirection vers la sélection immédiate du forfait (31. subscriptionPlan) [INDEX]

        $this->addFlash('success', 'Votre structure a été configurée avec succès. Choisissez votre formule SaaS.');
        return $this->redirectToRoute('public_agency_choose_plan');
    } //processRegister


   // src/Controller/Public/PublicAgencyRegistrationController.php

    /**
     * ÉTAPE 2 : EXPOSITION DES MODULES ET GRILLES DE RECRUTEMENT SUBSCRIPTIONPLAN [INDEX]
     */
    #[Route('/agency/choose-plan', name: 'public_agency_choose_plan', methods: ['GET'])]
    public function choosePlan(): Response
    {
        // 3. Référentiel d'usine des forfaits commerciaux du SaaS
        $platformPlans = [
            [
                'id' => 1,
                'name' => 'trial',
                'price' => 0.00,
                'currency' => 'USD',
                'duration_days' => 14, // Période d'évaluation standard de 2 semaines
                'max_branches' => 1,
                'max_users' => 2,
                'max_buses' => 3,
                'level' => 'Évaluation',
                'features' => [
                    'online_booking' => 'Réservation de billets basique au guichet',
                    'advanced_reports' => 'Statistiques d\'activité journalières'
                ]
            ],
            [
                'id' => 2,
                'name' => 'pro',
                'price' => 250.00,
                'currency' => 'USD',
                'duration_days' => 30,
                'max_branches' => 99,
                'max_users' => 99,
                'max_buses' => 99,
                'level' => 'Recommandé',
                'features' => ['online_booking' => 'Réservation en ligne synchrone', 'advanced_reports' => 'Statistiques avancées', 'shipment_module' => 'Gestion complète des colis & fret']
            ],
            [
                'id' => 1,
                'name' => 'basic',
                'price' => 75.00,
                'currency' => 'USD',
                'duration_days' => 30,
                'max_branches' => 1,
                'max_users' => 5,
                'max_buses' => 5,
                'level' => 'Starter',
                'features' => ['online_booking' => 'Réservation de billets basique', 'advanced_reports' => 'Rapports locaux limités']
            ],
        ];

        // 4. Extraction de tes entités réelles "30. Feature" du MCD pour nourrir ton 3ème Onglet
        $systemFeatures = [
            ['code' => 'online_booking', 'name' => 'Réservation en Ligne', 'description' => 'Vente de billets en temps réel avec sélection tactile sur Seatmap.', 'createdAt' => new \DateTime('2024-01-10'), 'status' => 'active'],
            ['code' => 'shipment_module', 'name' => 'Gestion des Colis & Fret', 'description' => 'Expédition, pesée et édition des bordereaux de colisage en gare.', 'createdAt' => new \DateTime('2024-02-15'), 'status' => 'active'],
            ['code' => 'advanced_reports', 'name' => 'Rapports & Audits Avancés', 'description' => 'Graphiques de performances financières et exportation des livres de caisses.', 'createdAt' => new \DateTime('2024-03-20'), 'status' => 'active'],
            ['code' => 'driver_app_api', 'name' => 'Interface API Chauffeurs', 'description' => 'Synchronisation des fiches de routes sur l\'application mobile conducteurs.', 'createdAt' => new \DateTime('2025-05-12'), 'status' => 'inactive']
        ];

        return $this->render('public/agency/choose_plan.html.twig', [
            'page' => 'agency',
            'system_features' => $systemFeatures,
            'platform_plans' => $platformPlans, // Injection cruciale !
        ]);
    } //choosePlan

    #[Route('/register-agency/subscribe/finalize', name: 'public_agency_subscribe_finalize', methods: ['POST'])]
    public function finalizeSubscription(): Response
    {
        $this->addFlash('success', 'Votre licence d\'exploitation a été provisionnée. Connectez-vous à présent.');
        return $this->redirectToRoute('public_login');
    } //finalizeSubscription
}
