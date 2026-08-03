<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CashierController extends AbstractController
{

    #[Route('/admin/agency/cash-register', name: 'admin_agency_cashier_index')]
    #[Route('/admin/agency/cash-register', name: 'admin_agency_cashier_dashboard')]
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

    #[Route('/admin/agency/cash-register/dashboard/general', name: 'admin_agency_cashier_dashboard_general')]
    public function general(Request $request): Response
    {
        return $this->render('admin/agency/cashier/general.html.twig', [
            'page' => 'cashier',
        ]);
    } //general

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


}
