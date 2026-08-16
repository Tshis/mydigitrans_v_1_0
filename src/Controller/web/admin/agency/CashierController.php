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
        //return $this->render('admin/agency/cashier/dashboard.html.twig', [
        return $this->render('admin/agency/cashier/session_open.html.twig', [
            'page' => 'cashier',
        ]);
    } //session_opening

    #[Route('/admin/agency/cash-register/dashboard', name: 'admin_agency_cashier_dashboard')]
    public function dashboard(Request $request): Response
    {
        return $this->render('admin/agency/cashier/dashboard.html.twig', [
            'page' => 'cashier',
        ]);
    } //dashboard

    #[Route('/admin/agency/cash-register/payment', name: 'admin_agency_cashier_payment')]
    public function payment(Request $request): Response
    {
        return $this->render('admin/agency/cashier/payment.html.twig', [
            'page' => 'cashier',
        ]);
    } //payment

    #[Route('/admin/agency/cash-register/expense', name: 'admin_agency_cashier_expense')]
    public function expense(Request $request): Response
    {
        return $this->render('admin/agency/cashier/expense.html.twig', [
            'page' => 'cashier',
        ]);
    } //expense

    #[Route('/admin/agency/cash-register/payment/code-de-paiement', name: 'admin_agency_cashier_show_payment')]
    public function show_payment(Request $request): Response
    {
        return $this->render('admin/agency/cashier/show_payment.html.twig', [
            'page' => 'cashier',
        ]);
    } //show_payment

    #[Route('/admin/agency/cash-register/expense/code-de-paiement', name: 'admin_agency_cashier_show_expense')]
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
