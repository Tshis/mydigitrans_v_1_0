<?php
// src/Controller/Admin/Platform/PlatformSubscriptionController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
                'id' => 1,
                'agency_name' => 'TransKin Express',
                'plan_name' => 'Premium Multitenant',
                'start_at' => new \DateTime('2025-01-15'),
                'end_at' => new \DateTime('2027-01-15'),
                'price' => 250.00,
                'status' => 'active'
            ],
            [
                'id' => 2,
                'agency_name' => 'Océan du Gabon',
                'plan_name' => 'Premium Multitenant',
                'start_at' => new \DateTime('2025-06-30'),
                'end_at' => new \DateTime('2026-12-30'),
                'price' => 250.00,
                'status' => 'active'
            ],
            [
                'id' => 3,
                'agency_name' => 'TransFleuve',
                'plan_name' => 'Basic',
                'start_at' => new \DateTime('2025-09-01'),
                'end_at' => new \DateTime('2026-09-01'),
                'price' => 75.00,
                'status' => 'expired'
            ]
        ];

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
                'id' => 3,
                'name' => 'premium',
                'price' => 490.00,
                'currency' => 'USD',
                'duration_days' => 30,
                'max_branches' => 99,
                'max_users' => 99,
                'max_buses' => 99,
                'level' => 'Entreprise',
                'features' => ['Toutes les fonctions Premium', 'Accès complet aux API système', 'Module de fret & colisage avancé', 'Console d\'audits comptables poussée', 'Gestionnaire de compte MyDigitrans dédié']
            ]
        ];

        // 4. Extraction de tes entités réelles "30. Feature" du MCD pour nourrir ton 3ème Onglet
        $systemFeatures = [
            ['code' => 'online_booking', 'name' => 'Réservation en Ligne', 'description' => 'Vente de billets en temps réel avec sélection tactile sur Seatmap.', 'createdAt' => new \DateTime('2024-01-10'), 'status' => 'active'],
            ['code' => 'shipment_module', 'name' => 'Gestion des Colis & Fret', 'description' => 'Expédition, pesée et édition des bordereaux de colisage en gare.', 'createdAt' => new \DateTime('2024-02-15'), 'status' => 'active'],
            ['code' => 'advanced_reports', 'name' => 'Rapports & Audits Avancés', 'description' => 'Graphiques de performances financières et exportation des livres de caisses.', 'createdAt' => new \DateTime('2024-03-20'), 'status' => 'active'],
            ['code' => 'driver_app_api', 'name' => 'Interface API Chauffeurs', 'description' => 'Synchronisation des fiches de routes sur l\'application mobile conducteurs.', 'createdAt' => new \DateTime('2025-05-12'), 'status' => 'inactive']
        ];


        return $this->render('admin/platform/subscription/index.html.twig', [
            'page' => 'subscription',
            'stats' => $stats,
            'platform_plans' => $platformPlans, // Injection cruciale !
            'subscriptions_list' => $subscriptionsList,
            'system_features' => $systemFeatures
        ]);
    }//index



    /**
     * ADD / PROLONGEMENT : CRÉATION D'UNE COUVERTURE DE LICENCE SAAS
     */
    #[Route('/admin/platform/subscriptions/add', name: 'admin_platform_subscription_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $agencyId = (int) $request->request->get('agency_id');
            $planId = (int) $request->request->get('plan_id');

            // Réception de tes barrières temporelles exactes du MCD
            $start_at = new \DateTimeImmutable($request->request->get('start_at'));
            $end_at = new \DateTimeImmutable($request->request->get('end_at'));
            $amount = (float) $request->request->get('price');
            $method = $request->request->get('payment_method');

            // --- PROTOCOLE D'INSERTION SÉCURISÉ EN COUPLAGE BDD DOCTRINE ---
            // 1. Persister "34. Invoice" (Statut: paid, type: subscription)
            // 2. Persister "33. subscription" lié à l'Invoice (start_at, end_at, status: active)
            // 3. Persister "40. subscriptionPayment" (amount, currency, method, status: success)
            // 4. Mettre à jour l'échéance 'expired_at' de la table "1. Agency" principale.

            $this->addFlash('success', 'La quittance d\'abonnement, l\'Invoice et le reçu de paiement ont été scellés avec succès dans le registre.');
            return $this->redirectToRoute('admin_platform_subscription_index');
        }

        // Liste des agences pour alimenter ton sélecteur
        $agencies = [
            ['id' => 1, 'name' => 'TransKin Express', 'country' => 'RD Congo'],
            ['id' => 2, 'name' => 'Océan du Gabon', 'country' => 'Gabon'],
            ['id' => 3, 'name' => 'TransFleuve', 'country' => 'Congo-Brazzaville'],
        ];

        return $this->render('admin/platform/subscription/add.html.twig', [
            'page' => 'subscription',
            'agencies' => $agencies,
            'is_renewal' => false // Permet de basculer l'affichage du Twig
        ]);
    } //add


    /**
     * SOUCHE 2 : PROLONGEMENT DÉDIÉ D'UNE LICENCE EXISTANTEVIA ROUTE [MCD 33]
     */
    #[Route('/admin/platform/subscriptions/{id}/renew', name: 'admin_platform_subscription_renew', methods: ['GET', 'POST'])]
    public function renew(int $id, Request $request): Response
    {
        // 1. Extraction de la souscription précédente à prolonger (Simulation BDD)
        $currentSubscription = [
            'id' => $id,
            'agency_id' => 1,
            'agency_name' => 'TransKin Express',
            'country' => 'RD Congo',
            'endAt' => new \DateTimeImmutable('2026-10-15'), // Date d'échéance actuelle [MNS]
            'plan_id' => 2,
            'price' => 250.00
        ];

        if ($request->isMethod('POST')) {
            // Reçoit les données de renouvellement
            $nextStartAt = new \DateTimeImmutable($request->request->get('start_at'));
            $nextEndAt = new \DateTimeImmutable($request->request->get('end_at'));

            // EN INTÉGRATION ORM : On persiste la nouvelle entité subscription chaînée chronologiquement
            $this->addFlash('success', sprintf('La licence de %s a été prolongée jusqu\'au %s.', $currentSubscription['agency_name'], $nextEndAt->format('d/m/Y')));
            return $this->redirectToRoute('admin_platform_subscription_index');
        }

        // Calcule automatiquement la suggestion de période (+30 jours après la fin actuelle)
        $suggestedStartAt = $currentSubscription['endAt'];
        $suggestedEndAt = $suggestedStartAt->modify('+30 days');

        // Liste des agences pour alimenter ton sélecteur
        $agencies = [
            ['id' => 1, 'name' => 'TransKin Express', 'country' => 'RD Congo'],
            ['id' => 2, 'name' => 'Océan du Gabon', 'country' => 'Gabon'],
            ['id' => 3, 'name' => 'TransFleuve', 'country' => 'Congo-Brazzaville'],
        ];

        return $this->render('admin/platform/subscription/add.html.twig', [
            'page' => 'subscription',
            'sub' => $currentSubscription,
            'suggested_start' => $suggestedStartAt,
            'suggested_end' => $suggestedEndAt,
            'agencies' => $agencies,
            'is_renewal' => true // Permet de basculer l'affichage du Twig
        ]);
    }//renew


    /**
     * ❌ PROTOCOLE DE SÉCURITÉ EN CAS D'ERREUR DE DATE : ANNULATION (Avoir comptable)
     * Si l'administrateur a validé 28j au lieu de 30j, cette action détruit les droits et coupe proprement.
     */
    #[Route('/admin/platform/subscriptions/{id}/cancel-error', name: 'admin_platform_subscription_cancel_error', methods: ['POST'])]
    public function cancelErrorInvoice(int $id): Response
    {
        // EN CONTEXTE ENREGISTRÉ ORM :
        // 1. $subscription = $em->find(Subscription::class, $id);
        //    $subscription->setStatus('canceled'); // Annulation des droits
        // 2. $invoice = $subscription->getInvoice();
        //    $invoice->setStatus('cancelled'); // Annulation légale de la facture
        // 3. Insérer une ligne dans "37. platformRefund" pour activer l'avoir comptable du montant erroné.
        // $em->flush();

        $this->addFlash('warning', 'La souscription erronée a été passée à [canceled] et son Invoice à [cancelled]. Vous pouvez réémettre une quittance parfaite.');
        return $this->redirectToRoute('admin_platform_subscription_index');
    } //cancelErrorInvoice



}
