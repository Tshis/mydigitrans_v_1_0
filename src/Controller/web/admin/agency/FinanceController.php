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

        // 1. Simulation des filtres d'agences (MCD Table 4)
        $branches = [
            ['id' => 1, 'name' => 'Kinshasa Centre (Guichet 1)'],
            ['id' => 2, 'name' => 'Succursale Goma'],
            ['id' => 3, 'name' => 'Succursale Matadi'],
        ];

        // 2. Statistique multi-devises des recettes cumulées (Entrants)
        $dailyRevenues = [
            ['code' => 'CDF', 'amount' => '2 950 000'],
            ['code' => 'USD', 'amount' => '1 450.00'],
        ];

        // 3. Statistique multi-devises des dépenses cumulées (Sorties)
        $dailyExpenses = [
            ['code' => 'CDF', 'amount' => '180 000'],
            ['code' => 'USD', 'amount' => '320.00'],
        ];

        // 4. Calcul dynamique du solde net provisoire par tiroir monétaire
        $dailyNets = [
            ['code' => 'CDF', 'amount' => '2 770 000'],
            ['code' => 'USD', 'amount' => '1 130.00'],
        ];

        // 5. Grand livre unifié des flux financiers (FinancialOperation)
        $financialOperations = [
            [
                'id'          => 1,
                'date'        => '13/08/2026',
                'time'        => '10h14',
                'reference'   => 'PAY-8942',
                'type'        => 'payment',
                'label'       => 'Billet de Voyage (Jean Kabamba)',
                'sub_label'   => 'Réservation #RES-3904',
                'mode'        => 'Mobile Money',
                'origin'      => 'M-Pesa (+243 812...)',
                'amount'      => '45.00',
                'currency'    => 'USD',
                'status'      => 'Confirmé'
            ],
            [
                'id'          => 401,
                'date'        => '13/08/2026',
                'time'        => '08h30',
                'reference'   => 'EXP-0401',
                'type'        => 'expense',
                'label'       => 'Achat Carburant Bus #01',
                'sub_label'   => 'Frais d\'exploitation de ligne',
                'mode'        => 'Espèces (Cash)',
                'origin'      => 'Kinshasa Centre',
                'amount'      => '250.00',
                'currency'    => 'USD',
                'status'      => 'pending'
            ],
            [
                'id'          => 2,
                'date'        => '13/08/2026',
                'time'        => '11h20',
                'reference'   => 'PAY-8943',
                'type'        => 'payment',
                'label'       => 'Expédition Colis Fret',
                'sub_label'   => 'Bordereau #COL-9023',
                'mode'        => 'Espèces (Cash)',
                'origin'      => 'Goma Terminal',
                'amount'      => '65 000',
                'currency'    => 'CDF',
                'status'      => 'Confirmé'
            ]
        ];


        return $this->render('admin/agency/finance/index.html.twig', [
            'page' => 'finances',
            'branches'             => $branches,
            'daily_revenues'       => $dailyRevenues,
            'daily_expenses'       => $dailyExpenses,
            'daily_nets'           => $dailyNets,
            'financial_operations' => $financialOperations,
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
