<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FinanceController extends AbstractController
{

    #[Route('/admin/agency/finances', name: 'admin_agency_finance_index')]
    public function index(Request $request): Response
    {
        return $this->render('admin/agency/finance/index.html.twig', [
            'page' => 'finances',
        ]);
    } //index

    #[Route('/admin/agency/finance/show/{id}/{type}', name: 'admin_agency_finance_show')]
    public function show(Request $request): Response
    {
        return $this->render('admin/agency/finance/show.html.twig', [
            'page' => 'finances',
            'type' => 'payment'
        ]);
    } //show

    #[Route('/admin/agency/finance/expense/add', name: 'admin_agency_finance_expense_add')]
    public function expense_add(Request $request): Response
    {
        return $this->render('admin/agency/finance/expense_add.html.twig', [
            'page' => 'finances',
        ]);
    } //expense_add

    #[Route('/admin/agency/fare/add', name: 'admin_agency_fare_add')]
    public function payment_log(Request $request): Response
    {


        return $this->render('admin/agency/finance/payment_log.html.twig', [
            'page' => 'paiement',
        ]);
    } //payment_log

    #[Route('/admin/agency/finance/cashier/journal/{code}', name: 'admin_agency_finance_journal')]
    public function journal_caissier(Request $request): Response
    {
        return $this->render('admin/agency/finance/journal_caissier.html.twig', [
            'page' => 'paiement',
        ]);
    } //journal



}
