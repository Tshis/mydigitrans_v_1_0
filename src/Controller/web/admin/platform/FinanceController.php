<?php
// src/Controller/Admin/Platform/PlatformCashController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FinanceController extends AbstractController
{
    /**
     * INDEX : GRAND LIVRE DE TRÉSORERIE CENTRALE ET STATUTS DES CAISSES [MCD 43/45]
     */
    #[Route('/admin/platform/finance', name: 'admin_platform_finance_index', methods: ['GET'])]
    public function index(): Response
    {
        // 1. Hydratation de ton entité "43. platformCashSession" couplée à sa caisse et sa branche d'attache
        $sessionsList = [
            [
                'id' => 701,
                'register_code' => 'REG-GOMBE-01',
                'branch_name' => 'Siège Gombe (Kinshasa)',
                'openedBy' => 'Mireille K.',
                'openedAt' => new \DateTime('-8 hours'),
                'closedAt' => null,
                'status' => 'opened',
                'closingBalanceExpected' => 1250.00,
                'closingBalanceDeclared' => 1250.00,
                'closingBalanceDifference' => 0.00,
                'currency' => 'USD'
            ],
            [
                'id' => 702,
                'register_code' => 'REG-POINTE-02',
                'branch_name' => 'Direction Pointe-Noire',
                'openedBy' => 'Guy L.',
                'openedAt' => new \DateTime('-2 days'),
                'closedAt' => new \DateTime('-1 day'),
                'status' => 'closed',
                'closingBalanceExpected' => 450.00,
                'closingBalanceDeclared' => 420.00,
                'closingBalanceDifference' => -30.00,
                'currency' => 'USD' // Écart négatif tracé !
            ]
        ];

        // 2. Hydratation chirurgicale de ton entité d'écritures "45. platformFinancialOperation"
        $operationsList = [
            [
                'id' => 9951,
                'operationType' => 'subscription_payment',
                'type' => 'in',
                'referenceType' => 'invoice',
                'referenceId' => 84,
                'amount' => 250.00,
                'currency' => 'USD',
                'createdAt' => new \DateTime('-2 hours'),
                'createdBy' => 'Système API',
                'description' => 'Encaissement de la licence mensuelle de l\'agence TransKin Express'
            ],
            [
                'id' => 9952,
                'operationType' => 'expense',
                'type' => 'out',
                'referenceType' => 'platformExpense',
                'referenceId' => 14,
                'amount' => 120.00,
                'currency' => 'USD',
                'createdAt' => new \DateTime('-5 hours'),
                'createdBy' => 'Serge M.',
                'description' => 'Achat de fournitures de bureau et papier thermique pour terminaux POS'
            ],
            [
                'id' => 9953,
                'operationType' => 'commission',
                'type' => 'out',
                'referenceType' => 'commissionPayment',
                'referenceId' => 401,
                'amount' => 142.50,
                'currency' => 'USD',
                'createdAt' => new \DateTime('-1 day'),
                'createdBy' => 'Trésorier',
                'description' => 'Règlement de la quittance de parrainage MLM de Dieudonné Ilunga'
            ]
        ];

        // COMPILATION COMPTABLE DES FLUX CUMULÉS DE LA PLATEFORME
        // 1. Vault : Somme des platformFinancialOperation (type=in, operationType=subscription_payment)
        // 2. Expense : Somme des platformFinancialOperation (type=out, operationType=expense)
        // 3. Debt : Somme des commissions (status=pending) pour l'audit des sorties à venir
        $cashSummary = [
            'vault_total' => 4850.00,
            'expense_total' => 1240.00,
        ];

        return $this->render('admin/platform/finance/index.html.twig', [
            'page' => 'finance',
            'cash_summary' => $cashSummary,
            'sessions_list' => $sessionsList,
            'operations_list' => $operationsList
        ]);
    } //index



    /**
     * 45. CASH IN : ENREGISTRER UNE RECETTE LIQUIDE DANS LE GRAND LIVRE [MCD 45]
     */
    #[Route('/admin/platform/finance/deposit', name: 'admin_platform_finance_deposit', methods: ['GET', 'POST'])]
    public function deposit(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $sessionId = (int) $request->request->get('cash_session_id');
            $invoiceId = (int) $request->request->get('reference_id');
            $amount = (float) $request->request->get('amount');
            $operationType = $request->request->get('operation_type');
            $description = $request->request->get('description');

            // --- DOUBLE PROTOCOLE DE PERSISTANCE EN BDD ---
            // 1. Instancier et persister "45. platformFinancialOperation"
            //    $op = new PlatformFinancialOperation();
            //    $op->setPlatformCashSession($em->find(PlatformCashSession::class, $sessionId));
            //    $op->setType('in'); // SÉCURITÉ : ENTITY DU FLUX IN !
            //    $op->setOperationType($operationType);
            //    $op->setReferenceType('invoice');
            //    $op->setReferenceId($invoiceId);
            //    $op->setAmount($amount);
            //    $op->setDescription($description);
            //    $op->setCreatedBy($this->getUser());

            // 2. Mettre à jour l'Invoice liée "34. Invoice" à 'paid' et hydrater "40. subscriptionPayment" à 'success'
            //    $invoice = $em->find(Invoice::class, $invoiceId);
            //    $invoice->setStatus('paid');
            //    $invoice->setPaidAt(new \DateTimeImmutable());

            // 3. $em->flush();

            $this->addFlash('success', 'L\'écriture de recette (IN) a été scellée. Le solde du tiroir-caisse a été mis à jour.');
            return $this->redirectToRoute('admin_platform_cash_index');
        }

        // Simulation des sessions ouvertes pour le sélecteur [MCD 43]
        $activeSessions = [
            ['id' => 701, 'register_name' => 'REG-GOMBE-01 (Coffre USD)', 'openedBy' => 'Mireille K.']
        ];

        // Simulation des Invoices en attente de paiement [MCD 34]
        $pendingInvoices = [
            ['id' => 84, 'number' => 'INV-2026-0084', 'agency_name' => 'TransKin Express', 'amount' => 250.00]
        ];

        return $this->render('admin/platform/finance/payment_form.html.twig', [
            'page' => 'finance',
            'active_sessions' => $activeSessions,
            'pending_invoices' => $pendingInvoices
        ]);
    } //dposit

    /**
     * 44/45. CASH OUT : ENREGISTRER UNE DÉPENSE ET UN DÉCAISSEMENT EN CAISSE [MCD 44/45]
     */
    #[Route('/admin/platform/finance/expense', name: 'admin_platform_finance_expense', methods: ['GET', 'POST'])]
    public function expense(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $sessionId = (int) $request->request->get('cash_session_id');
            $branchId = (int) $request->request->get('platform_branch_id');
            $amount = (float) $request->request->get('amount');
            $name = $request->request->get('name');
            $description = $request->request->get('description');

            // --- DOUBLE ENREGISTREMENT SÉCURISÉ EN BDD ---
            // 1. On persiste la dépense fixe "44. PlatfomExpense"
            //    $expense = new PlatfomExpense();
            //    $expense->setName($name); $expense->setAmount($amount); $expense->setStatus('paid');

            // 2. On injecte le flux de sortie "45. platformFinancialOperation" lié à la caisse
            //    $op = new PlatformFinancialOperation();
            //    $op->setType('out'); // VERROU EXCLUSIF FLUX SORTANT !
            //    $op->setOperationType('expense');
            //    $op->setAmount($amount);
            //    $op->setPlatformCashSession($em->find(PlatformCashSession::class, $sessionId));
            // $em->flush();

            $this->addFlash('danger', 'L\'écriture de dépense (OUT) a été scellée. Le montant a été retiré du tiroir-caisse.');
            return $this->redirectToRoute('admin_platform_cash_index');
        }

        // Simulation des caisses d'origines opérationnelles [MCD 42/43]
        $activeSessions = [
            ['id' => 701, 'register_name' => 'REG-GOMBE-01 (Coffre USD)', 'live_finance_balance' => 1250.00]
        ];

        // Simulation des branches centrales [MCD 41]
        $branches = [
            ['id' => 1, 'name' => 'Siège Principal Gombe', 'city' => 'Kinshasa'],
            ['id' => 2, 'name' => 'Succursale Littoral', 'city' => 'Pointe-Noire']
        ];

        return $this->render('admin/platform/finance/expense_form.html.twig', [
            'page' => 'finance',
            'active_sessions' => $activeSessions,
            'branches' => $branches
        ]);
    } //expense

}
