<?php

namespace App\Controller\web\admin\agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DocumentController extends AbstractController
{



    #[Route('/admin/agency/report/documents', name: 'admin_agency_report_document_index')]
    public function index(Request $request): Response
    {
        $currentType = $request->query->get('type', 'document');
        $docType = $request->query->get('doc_type', '');
        $status = $request->query->get('status', '');

        $branchesList = [
            ['code' => 'SUC-KIN-01', 'name' => 'Victoire - Rond Point'],
            ['code' => 'SUC-MAT-02', 'name' => 'Matadi Ville - Port']
        ];

        // Simulation de l'échéancier multi-devise de l'entité 10. BusDocuments
        $documents = [
            [
                'id' => 1,
                'busBrand' => 'Toyota',
                'busModel' => 'Coaster',
                'busPlate' => 'A-5678-DE',
                'type' => 'Assurance',
                'referenceNumber' => 'POL-SON-99482',
                'issuedAt' => new \DateTime('2025-09-14'),
                'expiredAt' => new \DateTime('2026-09-14'),
                'renewalCost' => 280000,
                'renewalCostCurrency' => 'CDF', // En Francs Congolais
                'status' => 'expired',
                'updatedBy' => 'Daniel Lukonu'
            ],
            [
                'id' => 2,
                'busBrand' => 'Mercedes-Benz',
                'busModel' => 'Sprinter',
                'busPlate' => 'A-1234-BC',
                'type' => 'contrôle technique',
                'referenceNumber' => 'CT-CE-44821',
                'issuedAt' => new \DateTime('2026-04-10'),
                'expiredAt' => new \DateTime('2026-10-10'),
                'renewalCost' => 120.00,
                'renewalCostCurrency' => 'USD', // En Dollars Américains
                'status' => 'valid',
                'updatedBy' => 'Kalonji Beya'
            ]
        ];

        return $this->render('admin/agency/report/document/index.html.twig', [
            'page' => 'report',
            'current_type' => $currentType,
            'current_doc_type' => $docType,
            'current_status' => $status,
            'branches_list' => $branchesList,
            'documents' => $documents,
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
            'current_branch' => ''
        ]);
    } //index

    #[Route('/admin/agency/report/documents/{id}/details', name: 'admin_agency_report_document_show')]
    public function show(int $id): Response
    {
        // Extraction unitaire de traçabilité d'audit  enrichie avec les données véhicule
        $document = [
            'id' => $id,
            'busBrand' => 'Toyota',        // Ajout
            'busModel' => 'Coaster',       // Ajout
            'busPlate' => 'A-5678-DE',     // Ajout
            'type' => 'Assurance',
            'referenceNumber' => 'POL-SON-99482',
            'issuedAt' => new \DateTime('2025-09-14'),
            'expiredAt' => new \DateTime('2026-09-14'),
            'status' => 'expired',
            'updatedAt' => new \DateTime('2025-09-14 11:24:00'),
            'createdAt' => new \DateTime('2025-09-14 11:24:00'),
            'createdBy' => 'Daniel Lukonu',
            'url' => '/uploads/documents/sonas-2025.pdf'
        ];

        return $this->render('admin/agency/report/document/show.html.twig', [
            'page' => 'report',
            'document' => $document
        ]);
    } //show

}
