<?php

namespace App\Controller\web\admin\agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CashierController extends AbstractController
{

    #[Route('/admin/agency/report/cashiers', name: 'admin_agency_report_cashier_index')]
    public function index(Request $request): Response
    {
        // Matrice d'audit pour le contrôle des fiches de caisse par agent
        $cashierMatrix = [
            [
                'code' => 'csh-xy-01',
                'cashierName' => 'Antoinette Mputu',
                'cashierCode' => 'csh-001-dggdj',
                'branchName' => 'Victoire',
                'ticketsCount' => 48,
                'total_amount' => [
                    ['amount' => 2160000, 'currency' => 'CDF'],
                    ['amount' => 140.00, 'currency' => 'USD'],
                ],
                'sessionStatus' => 'closed'
            ],
            [
                'code' => 'csh-xy-02',
                'cashierName' => 'Jean Mukendi',
                'cashierCode' => 'csh-002-dsho',
                'branchName' => 'Matadi',
                'ticketsCount' => 12,
                'total_amount' => [
                    ['amount' => 5400000, 'currency' => 'CDF'],
                ],
                'sessionStatus' => 'opened'
            ]
        ];

        $dateFrom = $request->query->get('date_from', '2026-08-01');
        $dateTo = $request->query->get('date_to', '2026-08-31');


        return $this->render('admin/agency/report/cashier/index.html.twig', [
            'page' => 'report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'current_cashier' => '',
            'cashier_matrix' => $cashierMatrix
        ]);
    } //index

    #[Route('/admin/agency/report/cashier/{code}/{from}/{to}', name: 'admin_agency_report_cashier_show')]
    public function show(string $code, string $from, string $to, Request $request): Response
    {



        $cashier = [
            'code' => $code,
            'name' => 'Antoinette Mputu',
            'sessions' => [

                [
                    'id' => 567,
                    'openedAt' => 2026 - 01 - 01,
                    'closedAt' => 2026 - 01 - 01,
                    'currency' => 'CDF',
                    'expected' => 5400000,
                    'received' => 5400000,
                    'status' => 'closed',
                    'statusLabel' => 'Fermée',
                ],
                [
                    'id' => 567,
                    'openedAt' => 2026 - 01 - 01,
                    'closedAt' => 2026 - 01 - 01,
                    'currency' => 'USD',
                    'expected' => 200,
                    'received' => 150,
                    'status' => 'closed',
                    'statusLabel' => 'Fermée',
                ],
                [
                    'id' => 577,
                    'openedAt' => 2026 - 03 - 04,
                    'closedAt' => null,
                    'currency' => 'USD',
                    'expected' => 6700,
                    'received' => 6700,
                    'status' => 'opened',
                    'statusLabel' => 'Ouverte',
                ],

            ]
        ];

        $dateFrom = $from;
        $dateTo = $to;


        return $this->render('admin/agency/report/cashier/show.html.twig', [
            'page' => 'report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'cashier' => $cashier
        ]);
    } //show
}
