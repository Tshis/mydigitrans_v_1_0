<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CashierController extends AbstractController
{

    #[Route('/admin/agency/cash-register', name: 'admin_agency_cashier_index')]
    public function index(Request $request): Response
    {
        $session = "open";

        if ($session === "await") {
            return $this->redirectToRoute('admin_agency_cashier_session_open');
        }

        if ($session === "close") {
            return $this->redirectToRoute('admin_agency_cashier_awaiting_init');
        }

        return $this->redirectToRoute('admin_agency_cashier_dashboard');
    } //index

    #[Route('/admin/agency/cash-register/create', name: 'admin_agency_cashier_add')]
    public function add(Request $request): Response
    {

        return $this->render('admin/agency/cashier/add.html.twig', [
            'page' => 'cashier',
        ]);
    } //add

    #[Route('/admin/agency/cash-register/await/init', name: 'admin_agency_cashier_awaiting_init')]
    public function awaiting_init(Request $request): Response
    {

        return $this->render('admin/agency/cashier/awaiting_init.html.twig', [
            'page' => 'cashier',
        ]);
    } //awaiting_init


    #[Route('/admin/agency/cash-register/open-session', name: 'admin_agency_cashier_session_open')]
    public function session_opening(Request $request): Response
    {
        // Simulation des montants alloués au terminal par le gérant
        $activeCurrencies = [
            [
                'code' => 'CDF',
                'expected_amount' => '50000',
            ],
            [
                'code' => 'USD',
                'expected_amount' => '100',
            ],
            [
                'code' => 'EUR',
                'expected_amount' => '0',
            ],
        ];

        //return $this->render('admin/agency/cashier/dashboard.html.twig', [
        return $this->render('admin/agency/cashier/session_open.html.twig', [
            'page' => 'cashier',
            'active_currencies' => $activeCurrencies
        ]);
    } //session_opening

    #[Route('/admin/agency/cash-register/dashboard', name: 'admin_agency_cashier_dashboard')]
    public function dashboard(Request $request): Response
    {


        // 1. Simulation des fonds réels actuellement présents dans le tiroir physique
        $vaultBalances = [
            [
                'code'   => 'CDF',
                'amount' => '1 425 000',
            ],
            [
                'code'   => 'USD',
                'amount' => '450.00',
            ],
            [
                'code'   => 'EUR',
                'amount' => '120.00',
            ],
        ];

        // 2. Simulation du cumul des fiches de décaissement (sorties de caisse approuvées)
        $dailyExpenses = [
            [
                'code'   => 'CDF',
                'amount' => '85 000',
            ],
            [
                'code'   => 'USD',
                'amount' => '30.00',
            ],
        ];



        return $this->render('admin/agency/cashier/dashboard.html.twig', [
            'page' => 'cashier',
            'vault_balances' => $vaultBalances,
            'daily_expenses' => $dailyExpenses,
        ]);
    } //dashboard

    #[Route('/admin/agency/cash-register/payment', name: 'admin_agency_cashier_payment')]
    public function payment(Request $request): Response
    {
        // Simulation des caisses/devises actuellement actives pour cette succursale
        $activeCurrencies = [
            ['code' => 'CDF', 'name' => 'Franc Congolais'],
            ['code' => 'USD', 'name' => 'Dollar Américain'],
            ['code' => 'EUR', 'name' => 'Euro'],
            ['code' => 'CFA', 'name' => 'Franc CFA'],
        ];

        return $this->render('admin/agency/cashier/payment.html.twig', [
            'page' => 'cashier',
            'active_currencies' => $activeCurrencies
        ]);
    } //payment

    #[Route('/admin/agency/cash-register/expense', name: 'admin_agency_cashier_expense')]
    public function expense(Request $request): Response
    {

        // 1. Solde en temps réel des tiroirs physiques de l'agent
        $vaultBalances = [
            ['code' => 'CDF', 'amount' => '1 425 000'],
            ['code' => 'USD', 'amount' => '450.00'],
        ];

        // 2. Fiches de dépenses validées par la direction en attente d'exécution de cash
        $pendingExpenses = [
            [
                'reference'       => 'EXP-2026-0401',
                'date_ordered'    => 'Aujourd\'hui, 08h30',
                'authorizer'      => 'Alphonse Kalonji',
                'authorizer_role' => 'Gérant de Succursale',
                'beneficiary'     => 'Chauffeur : Jean Mukendi',
                'motif'           => 'Achat Carburant Bus #01 (Station Engen Limete)',
                'amount'          => '250.00',
                'amount_raw'      => '250',
                'currency'        => 'USD',
                'status'          => 'PENDING (Approuvé)'
            ],
            [
                'reference'       => 'EXP-2026-0402',
                'date_ordered'    => 'Aujourd\'hui, 10h12',
                'authorizer'      => 'Alphonse Kalonji',
                'authorizer_role' => 'Gérant de Succursale',
                'beneficiary'     => 'Secrétariat de Gare',
                'motif'           => 'Achat rames de papiers et encre pour tickets',
                'amount'          => '60 000',
                'amount_raw'      => '60000',
                'currency'        => 'CDF',
                'status'          => 'PENDING (Approuvé)'
            ]
        ];


        return $this->render('admin/agency/cashier/expense.html.twig', [
            'page' => 'cashier',
            'pending_count'    => count($pendingExpenses),
            'vault_balances'   => $vaultBalances,
            'pending_expenses' => $pendingExpenses,
        ]);
    } //expense

    #[Route('/admin/agency/cash-register/payment/code-de-paiement', name: 'admin_agency_cashier_show_payment')]
    public function show_payment(Request $request): Response
    {
        return $this->render('admin/agency/cashier/show_payment.html.twig', [
            'page' => 'cashier',
        ]);
    } //show_payment

    #[Route('/admin/agency/cash-register/expense/code-de-decaissement', name: 'admin_agency_cashier_show_expense')]
    public function show_expense(Request $request): Response
    {
        return $this->render('admin/agency/cashier/show_expense.html.twig', [
            'page' => 'cashier',
        ]);
    } //show_expense

    #[Route('/admin/agency/cash-register/dashboard/global', name: 'admin_agency_cashier_dashboard_global')]
    public function global(Request $request): Response
    {
        return $this->render('admin/agency/cashier/global.html.twig', [
            'page' => 'cashier',
        ]);
    } //global

    #[Route('/admin/agency/cash-register/dashboard/branch', name: 'admin_agency_cashier_dashboard_branch')]
    public function branch(Request $request): Response
    {
        return $this->render('admin/agency/cashier/branch.html.twig', [
            'page' => 'cashier',
        ]);
    } //branch

    #[Route('/admin/agency/cash-register/session/closing', name: 'admin_agency_cashier_session_closing')]
    public function session_closing(Request $request): Response
    {
        return $this->render('admin/agency/cashier/session_close.html.twig', [
            'page' => 'cashier',
        ]);
    } //session_closing

    #[Route('/admin/agency/cash-register/session/{code}/closing/validation', name: 'admin_agency_cashier_session_closing_validation')]
    public function session_closing_validation(Request $request): Response
    {
        // Simulation statique des lignes de la table CashSessionBalance liées à cette session
        $sessionBalances = [
            [
                'currency'  => 'CDF',
                'closingBalanceExpected'  => '1 450 000',
                'closingBalanceDeclared'  => '1 450 000',
                'closingBalanceDifference'       => '0',
            ],
            [
                'currency'  => 'USD',
                'closingBalanceExpected'  => '450.00',
                'closingBalanceDeclared'  => '435.00',
                'closingBalanceDifference' => '-15.00', // Écart négatif (Manquant de caisse)
            ],
            [
                'currency'  => 'EUR',
                'closingBalanceExpected'  => '120.00',
                'closingBalanceDeclared'  => '125.00',
                'closingBalanceDifference'       => '+5',
            ],
        ];



        return $this->render('admin/agency/cashier/session_closing_validation.html.twig', [
            'page' => 'cashier',
            'session_balances' => $sessionBalances
        ]);
    } //session_closing_validation

    #[Route('/admin/agency/cash-register/session/{code}/initialization', name: 'admin_agency_cashier_session_init')]
    public function session_init(Request $request): Response
    {
        $currencies = ['CDF', 'USD', 'EUR'];
        return $this->render('admin/agency/cashier/session_init.html.twig', [
            'page' => 'cashier',
            'active_currencies' => $currencies
        ]);
    } //session_init


}
