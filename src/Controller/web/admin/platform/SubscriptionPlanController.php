<?php
// src/Controller/Admin/Platform/PlatformSubscriptionController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionPlanController extends AbstractController
{


    /**
     * 31. ADD PLAN : CRÉATION D'UN PLAN COMMERCIAL D'USINE
     */
    #[Route('/admin/platform/subscription-plans/add', name: 'admin_platform_plan_add', methods: ['GET', 'POST'])]
    public function addPlan(Request $request): Response
    {
        // 1. Simulation du Catalogue Global de tes entités "30. Feature" [MCD 30]
        $systemFeatures = [
            [
                'code' => 'online_booking',
                'name' => 'Réservation en Ligne',
                'description' => 'Permet aux agences de vendre des billets en temps réel avec sélection tactile sur Seatmap.'
            ],
            ['code' => 'shipment_module', 'name' => 'Gestion des Colis & Fret', 'description' => 'Active le module d\'expédition, pesée et édition des bordereaux de colisage en gare.'],
            ['code' => 'advanced_reports', 'name' => 'Rapports & Audits Avancés', 'description' => 'Débloque les graphiques de performances financières et l\'exportation de livres de caisses.'],
            ['code' => 'driver_app_api', 'name' => 'Interface API Chauffeurs', 'description' => 'Permet la synchronisation des fiches de routes sur l\'application mobile des conducteurs.']
        ];

        if ($request->isMethod('POST')) {
            // EN INTÉGRATION ORM FINALE :
            // $plan = new SubscriptionPlan();
            // $plan->setName($request->request->get('name'));
            // $plan->setPrice((float) $request->request->get('price'));
            // $plan->setDurationDays((int) $request->request->get('duration_days'));
            // $plan->setMaxBranches((int) $request->request->get('max_branches'));
            // $plan->setMaxBuses((int) $request->request->get('max_buses'));
            // $plan->setMaxUsers((int) $request->request->get('max_users'));
            // $plan->setStatus($request->request->has('status') ? 'active' : 'inactive');
            // $em->persist($plan); $em->flush();

            $this->addFlash('success', 'Le nouveau forfait d\'usine a été déployé dans le catalogue mondial.');
            return $this->redirectToRoute('admin_platform_subscription_index');
        }

        return $this->render('admin/platform/subscription_plan/form.html.twig', [
            'page' => 'subscription',
            'system_features' => $systemFeatures,
            'is_edit' => false
        ]);
    }//addPlan

    /**
     * 31. EDIT PLAN : AJUSTEMENT TECHNIQUE DES RESTRICTIONS ET PRIX D'UN PLAN
     */
    #[Route('/admin/platform/subscription-plans/{id}/edit', name: 'admin_platform_plan_edit', methods: ['GET', 'POST'])]
    public function editPlan(int $id, Request $request): Response
    {
        // Simulation d'une offre existante extraite de ta table 31
        $plan = [
            'id' => $id,
            'name' => 'pro',
            'price' => 250.00,
            'currency' => 'USD',
            'duration_days' => 30,
            'max_branches' => 99,
            'max_buses' => 99,
            'max_users' => 99,
            'status' => 'active'
        ];



        if ($request->isMethod('POST')) {
            $this->addFlash('success', 'Les quotas et restrictions du forfait système ont été mis à jour.');
            return $this->redirectToRoute('admin_platform_subscription_index');
        }



        // 1. Simulation du Catalogue Global de tes entités "30. Feature" [MCD 30]
        $systemFeatures = [
            [
                'code' => 'online_booking',
                'name' => 'Réservation en Ligne',
                'description' => 'Permet aux agences de vendre des billets en temps réel avec sélection tactile sur Seatmap.'
            ],
            ['code' => 'shipment_module', 'name' => 'Gestion des Colis & Fret', 'description' => 'Active le module d\'expédition, pesée et édition des bordereaux de colisage en gare.'],
            ['code' => 'advanced_reports', 'name' => 'Rapports & Audits Avancés', 'description' => 'Débloque les graphiques de performances financières et l\'exportation de livres de caisses.'],
            ['code' => 'driver_app_api', 'name' => 'Interface API Chauffeurs', 'description' => 'Permet la synchronisation des fiches de routes sur l\'application mobile des conducteurs.']
        ];

        // 3. Extraction des codes actuellement liés à ce forfait dans ta table associative "32. PlanFeature"
        $planActiveFeatures = ['online_booking', 'shipment_module', 'advanced_reports'];

        return $this->render('admin/platform/subscription_plan/form.html.twig', [
            'page' => 'subscription',
            'is_edit' => true,
            'plan' => $plan,
            'system_features' => $systemFeatures,
            'plan_active_features' => $planActiveFeatures
        ]);
    } //editPlan


    // src/Controller/Admin/Platform/PlatformSubscriptionController.php

// ... (Conserve tes autres méthodes d'abonnements, plans et invoices)

    /**
     * 30. ADD FEATURE : DÉCLARATION D'UN NOUVEAU VERROU APPLICATIF SYSTÈME
     */
    #[Route('/admin/platform/features/add', name: 'admin_platform_feature_add', methods: ['GET', 'POST'])]
    public function addFeature(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // EN LOGIQUE DE SOUCHE DOCTRINE :
            // $feature = new Feature();
            // $feature->setCode($request->request->get('code'));
            // $feature->setName($request->request->get('name'));
            // $feature->setDescription($request->request->get('description'));
            // $feature->setStatus($request->request->has('status') ? 'active' : 'inactive');
            // $feature->setCreatedAt(new \DateTimeImmutable());
            // $feature->setCreatedBy($this->getUser());
            // $em->persist($feature); $em->flush();

            $this->addFlash('success', 'La nouvelle fonctionnalité d\'usine a été greffée au catalogue mondial.');
            return $this->redirectToRoute('admin_platform_subscription_index');
        }

        return $this->render('admin/platform/feature/form.html.twig', [
            'page' => 'subscription',
            'is_edit' => false
        ]);
    }//addFeature

    /**
     * 30. EDIT FEATURE : MODIFICATION DU DESCRIPTIF OU DU STATUT DU MODULE
     */
    #[Route('/admin/platform/features/{code}/edit', name: 'admin_platform_feature_edit', methods: ['GET', 'POST'])]
    public function editFeature(string $code, Request $request): Response
    {
        // Extraction de l'entité existante depuis ton dictionnaire de base de données (Table 30)
        $feature = [
            'code' => $code,
            'name' => 'Réservation en Ligne',
            'description' => 'Permet aux agences de vendre des billets en temps réel avec sélection tactile sur Seatmap.',
            'status' => 'active'
        ];

        if ($request->isMethod('POST')) {
            // Mise à jour de l'entité
            $this->addFlash('success', sprintf('Le module technique "%s" a été mis à jour de manière sécurisée.', $code));
            return $this->redirectToRoute('admin_platform_subscription_index');
        }

        return $this->render('admin/platform/feature/form.html.twig', [
            'page' => 'subscription',
            'is_edit' => true,
            'feature' => $feature
        ]);
    } //editFeature
}
