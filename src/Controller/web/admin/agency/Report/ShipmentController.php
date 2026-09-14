<?php

namespace App\Controller\web\admin\agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShipmentController extends AbstractController
{
    #[Route('/admin/agency/report/shipment/home', name: 'admin_agency_report_shipment_index')]
    public function Index(Request $request): Response
    {
        $shipmentMatrix = [
            ['branchCode' => 'SUC-KIN-01', 'branchName' => 'Victoire', 'totalColis' => 340, 'totalWeight' => 4250, 'prepaidRevenue' => 1850000, 'postpaidRevenue' => 650000, 'currency' => 'CDF'],
            ['branchCode' => 'SUC-MAT-02', 'branchName' => 'Matadi Ville', 'totalColis' => 124, 'totalWeight' => 1890, 'prepaidRevenue' => 920000, 'postpaidRevenue' => 140000, 'currency' => 'CDF']
        ];
        return $this->render('admin/agency/report/shipment/index.html.twig', [
            'page' => 'report',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'shipment_matrix' => $shipmentMatrix
        ]);
    } //index

    #[Route('/admin/agency/reports/shipment/{branch_code}/show', name: 'admin_agency_report_shipment_show')]
    public function Show(string $branch_code): Response
    {
        $items = [
            [
                'code' => 'CL-9942',
                'sender' => 'Kalonji B.',
                'receiver' => 'Mputu A.',
                'label' => 'Sac de marchandises',
                'weight' => 45,
                'paymentMethod' => 'prepaid',
                'price' => 25000,
                'currency' => 'CDF',
                'createdAt' => '2026-06-06'
            ]
        ];
        return $this->render('admin/agency/report/shipment/show.html.twig', [
            'page' => 'report',
            'branch_code' => $branch_code,
            'items' => $items,
            'date_from' => '2026 - 08 - 31',
            'date_to' => '2026 - 09 - 14',
        ]);
    } //show

}
