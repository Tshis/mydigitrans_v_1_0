<?php
// src/Controller/Admin/Platform/PlatformCommissionController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommissionController extends AbstractController
{
    /**
     * INDICES PAR PARALLELES ET CONFIGURATIONS DES REVENUS RESEAUX INTERNATIONAUX 
     */
    #[Route('/admin/platform/commissions', name: 'admin_platform_commission_index', methods: ['GET'])]
    public function index(): Response
    {
        //0 Commission
        $commission = [
            'total' => 1670,
            'paid' => 1570,
            'toPay' => 100,
        ];

        // 1. Hydratation de ton entité "47. commission" couplée à ton user bénéficiaire
        $commissionsList = [
            [
                'id' => 8801,
                'user_name' => 'Dieudonné Ilunga',
                'sourceType' => 'Ticket',
                'sourceId' => 451,
                'level' => 1,
                'percentage' => 5.0,
                'amount' => 12.50,
                'currency' => 'USD',
                'createdAt' => new \DateTime('now'),
                'status' => 'pending'
            ],
            [
                'id' => 8802,
                'user_name' => 'Blaise Mvumbi',
                'sourceType' => 'Shipment',
                'sourceId' => 902,
                'level' => 2,
                'percentage' => 2.5,
                'amount' => 4.20,
                'currency' => 'USD',
                'createdAt' => new \DateTime('-3 hours'),
                'status' => 'approved'
            ],
            [
                'id' => 8803,
                'user_name' => 'Gabriel Nsimba',
                'sourceType' => 'subscription',
                'sourceId' => 12,
                'level' => 3,
                'percentage' => 1.0,
                'amount' => 2.50,
                'currency' => 'USD',
                'createdAt' => new \DateTime('-1 day'),
                'status' => 'paid'
            ]
        ];

        // 2. Hydratation de ton entité "46. commissionRule" (Taux d'usines system)
        $rulesList = [
            ['id' => 1, 'name' => 'Commissions Guichets Directs', 'sourceType' => 'Ticket', 'level' => 1, 'maxLevel' => 1, 'percentage' => 5.0, 'isActive' => true],
            ['id' => 2, 'name' => 'Parrainage MLM Rang Inférieur', 'sourceType' => 'Ticket', 'level' => 2, 'maxLevel' => 5, 'percentage' => 2.5, 'isActive' => true],
            ['id' => 3, 'name' => 'Commissions Colisage National', 'sourceType' => 'Shipment', 'level' => 1, 'maxLevel' => 1, 'percentage' => 4.0, 'isActive' => false],
        ];

        // 3. NOUVEAU - Hydratation de ton entité "48. commissionPayment" du MCD [MCD 48]
        $paymentsHistory = [
            [
                'id' => 401,
                'user_name' => 'Dieudonné Ilunga',
                'reference' => 'MP-REF-88492',
                'method' => 'Mobile Money (M-Pesa)',
                'amount' => 142.50,
                'currency' => 'USD',
                'paidAt' => new \DateTime('-2 days'),
                'status' => 'success'
            ],
            [
                'id' => 402,
                'user_name' => 'Blaise Mvumbi',
                'reference' => 'CSH-VOL-1102',
                'method' => 'Espèces / Caisse Centrale',
                'amount' => 38.00,
                'currency' => 'USD',
                'paidAt' => new \DateTime('-1 week'),
                'status' => 'success'
            ]
        ];

        return $this->render('admin/platform/commission/index.html.twig', [
            'page' => 'commission',
            'commissions_list' => $commissionsList,
            'commission' => $commission,
            'rules_list' => $rulesList,
            'payments_history' => $paymentsHistory
        ]);
    }//index

    /**
     * ADD RULE : CREATION D'UNE NOUVELLE REGLE DE COMMISSION
     */
    #[Route('/admin/platform/commission/nouvelle-regle', name: 'admin_platform_commission_rule_add')]
    public function addRule(): Response
    {
        return $this->render('admin/platform/commission/rule_form.html.twig', [
            'page' => 'commission',
            'is_edit' => false,
        ]);
    }//addRule


    /**
     * 46. EDIT RULE : AJUSTEMENT DES ALGORITHMES SAAS PAR NIVEAU
     */
    #[Route('/admin/platform/commissions/rules/{id}/edit', name: 'admin_platform_commission_rule_edit', methods: ['GET', 'POST'])]
    public function editRule(int $id, Request $request): Response
    {
        $rule = ['id' => $id, 'name' => 'Commissions Guichets Directs', 'sourceType' => 'Ticket', 'level' => 1, 'maxLevel' => 1, 'percentage' => 5.0, 'isActive' => true];

        if ($request->isMethod('POST')) {
            $this->addFlash('success', 'La règle d\'attribution d\'usine a été recalculée.');
            return $this->redirectToRoute('admin_platform_commission_index');
        }

        return $this->render('admin/platform/commission/rule_form.html.twig', [
            'page' => 'commission',
            'is_edit' => true,
            'rule' => $rule
        ]);
    }//edit_rule


    /**
     * TOGGLE RULE : CHANGEMENT DE STATUT D'UNE REGLE DE COMMISSION
     */
    #[Route('/admin/platform/commissions/rule/{id}/change-status', name: 'admin_platform_commission_rule_toggle', methods: ['GET', 'POST'])]
    public function toggleRuleStatus(int $id): Response
    {
        // LOGIQUE ORM DE COMMUTATION :
        // $rule = $em->find(CommissionRule::class, $id);
        // $rule->setIsActive(!$rule->isIsActive());
        // $em->flush();

        $this->addFlash('success', 'Le statut opérationnel de la règle a été basculé avec succès.');
        return $this->redirectToRoute('admin_platform_commission_index');
    }//toggleRuleStatus

    /**
     * 48. PROCESSE PAIEMENT : ENREGISTRER UNE SORTIE DE COMPTE POUR UN AGENT
     */
    #[Route('/admin/platform/commissions/pay', name: 'admin_platform_commission_pay', methods: ['GET', 'POST'])]
    public function payCommissions(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // EN LOGIQUE DOCTRINE : 
            // 1. Créer la ligne commissionPayment (status: success)
            // 2. Récupérer toutes les lignes de 'commission' pending de cet user
            // 3. Boucler pour insérer chaque ligne dans commissionPaymentItem et basculer la commission à 'paid'
            $this->addFlash('success', 'L\'ordre de versement a été exécuté et les écritures correspondantes ont été archivées.');
            return $this->redirectToRoute('admin_platform_commission_index');
        }

        $users = [
            ['id' => 45, 'name' => 'Dieudonné Ilunga', 'pending_balance' => 142.50],
            ['id' => 52, 'name' => 'Blaise Mvumbi', 'pending_balance' => 38.00]
        ];

        return $this->render('admin/platform/commission/payment_form.html.twig', [
            'page' => 'commission',
            'users' => $users
        ]);
    }//payment_commission

    /**
     * 49. AUDIT REÇU : INSPECTER UN PAIEMENT ET SA DECOMPOSITION DE LIGNES (commissionPaymentItem)
     */
    #[Route('/admin/platform/commissions/payments/{id}/show', name: 'admin_platform_commission_payment_show', methods: ['GET'])]
    public function showPaymentReceipt(int $id): Response
    {
        $payment = ['id' => $id, 'user_name' => 'Dieudonné Ilunga', 'method' => 'Mobile Money', 'reference' => 'MP-REF-88492', 'amount' => 142.50, 'currency' => 'USD', 'status' => 'success'];

        // Hydratation de ton entité associative "49. commissionPaymentItem" [MCD 49]
        $items = [
            ['commission_id' => 8801, 'sourceType' => 'Ticket', 'sourceId' => 451, 'level' => 1, 'amount' => 12.50],
            ['commission_id' => 8942, 'sourceType' => 'Ticket', 'sourceId' => 452, 'level' => 1, 'amount' => 110.00],
            ['commission_id' => 9012, 'sourceType' => 'Shipment', 'sourceId' => 102, 'level' => 1, 'amount' => 20.00],
        ];

        return $this->render('admin/platform/commission/payment_show.html.twig', [
            'page' => 'commission',
            'payment' => $payment,
            'items' => $items
        ]);
    } //payment_show



    /**
     * APPROUVE COMM : CHANGEMENT DE STATUT D'UNE REGLE DE COMMISSION
     */
    #[Route('/admin/platform/commission/{refernce}/approuve', name: 'admin_platform_commission_approbation', methods: ['GET', 'POST'])]
    public function approbation(string $reference): Response
    {
        // LOGIQUE ORM DE COMMUTATION :
        // $rule = $em->find(CommissionRule::class, $id);
        // $rule->setIsActive(!$rule->isIsActive());
        // $em->flush();

        $this->addFlash('success', 'Le statut opérationnel de la règle a été basculé avec succès.');
        return $this->redirectToRoute('admin_platform_commission_index');
    } //approbation


    /**
     * 47. APPROBATION DE MASSE : COMMUTATION CHIRURGICALE EN BLOC DES LIGNES PENDING [MCD 47]
     */
    #[Route('/admin/platform/commissions/user/{userId}/approve-all', name: 'admin_platform_commission_approve_all', methods: ['POST'])]
    public function approveAllPendingForUser(int $userId): Response
    {
        // EN CONTEXTE DOCTRINE ORM REEL :
        // On effectue une seule requête UPDATE optimisée sur le serveur SGBD
        // $qb = $em->createQueryBuilder();
        // $qb->update(Commission::class, 'c')
        //    ->set('c.status', ':approvedStatus')
        //    ->set('c.approvedAt', ':now')
        //    ->set('c.approvedBy', ':adminUser')
        //    ->where('c.user = :userId')
        //    ->andWhere('c.status = :pendingStatus')
        //    ->setParameter('approvedStatus', 'approved')
        //    ->setParameter('now', new \DateTimeImmutable())
        //    ->setParameter('adminUser', $this->getUser())
        //    ->setParameter('userId', $userId)
        //    ->setParameter('pendingStatus', 'pending');
        // $qb->getQuery()->execute();

        $this->addFlash('success', 'Toutes les lignes de gains de cet apporteur ont été approuvées et sont prêtes pour le décaissement.');
        return $this->redirectToRoute('admin_platform_commission_index');
    } //approveAllPendingForUser


    /**
     * 47. FULL APPROBATION SÉCURISÉE : VALIDATION EXCLUSIVE DES LIGNES COMPORTANT +7 JOURS DE RETENTION [MCD 47]
     */
    #[Route('/admin/platform/commissions/approve-global-network', name: 'admin_platform_commission_approve_global_network', methods: ['POST'])]
    public function approveGlobalNetworkPending(Request $request): Response
    {
        // Calcul mathématique exact de la date barrière (Aujourd'hui moins 7 jours complets)
        $limitDate = new \DateTimeImmutable('-7 days');

        // EN CONTEXTE REEL DOCTRINE ORM :
        // Le système filtre strictement sur la date de création 'createdAt'
        // $qb = $em->createQueryBuilder();
        // $qb->update(Commission::class, 'c')
        //    ->set('c.status', ':approvedStatus')
        //    ->set('c.approvedAt', ':now')
        //    ->set('c.approvedBy', ':admin')
        //    ->where('c.status = :pendingStatus')
        //    ->andWhere('c.createdAt <= :limitDate') // FILTRE DE SÉCURITÉ CRUCIAL DES 7 JOURS !
        //    ->setParameter('approvedStatus', 'approved')
        //    ->setParameter('now', new \DateTimeImmutable())
        //    ->setParameter('admin', $this->getUser())
        //    ->setParameter('pendingStatus', 'pending')
        //    ->setParameter('limitDate', $limitDate);
        // $qb->getQuery()->execute();

        $this->addFlash('success', sprintf(
            'L\'approbation automatique a été exécutée. Seules les commissions calculées avant le %s ont été validées et débloquées pour le paiement.',
            $limitDate->format('d/m/Y H:i')
        ));

        return $this->redirectToRoute('admin_platform_commission_index');
    } //approveGlobalNetworkPending




}
