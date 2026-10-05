<?php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvoiceController extends AbstractController
{

    /**
     * 34. INDEX : GRAND LIVRE DE FACTURATION ET SUIVI DU CHIFFRE D'AFFAIRES DU RESEAU [MCD 34]
     */
    #[Route('/admin/platform/invoices', name: 'admin_platform_invoice_index', methods: ['GET'])]
    public function index(): Response
    {
        // Hydratation globale de ton entité "34. Invoice" reprise de ton MCD
        $globalInvoicesList = [
            [
                'id' => 101,
                'invoiceNumber' => 'INV-2026-0084',
                'agency_name' => 'TransKin Express',
                'type' => 'subscription',
                'currency' => 'USD',
                'status' => 'paid',
                'issuedAt' => new \DateTime('2026-09-15'),
                'periodStart' => new \DateTime('2026-09-15'),
                'periodEnd' => new \DateTime('2026-10-15'),
                'total' => 250.00,
                'balanceDue' => 0.00
            ],
            [
                'id' => 102,
                'invoiceNumber' => 'INV-2026-0092',
                'agency_name' => 'Océan du Gabon',
                'type' => 'platformFee',
                'currency' => 'USD',
                'status' => 'issued',
                'issuedAt' => new \DateTime('2026-09-25'),
                'periodStart' => new \DateTime('2026-09-01'),
                'periodEnd' => new \DateTime('2026-09-25'),
                'total' => 184.50,
                'balanceDue' => 184.50
            ],
            [
                'id' => 103,
                'invoiceNumber' => 'INV-2026-0041',
                'agency_name' => 'TransFleuve',
                'type' => 'mixed',
                'currency' => 'USD',
                'status' => 'overdue',
                'issuedAt' => new \DateTime('2026-08-15'),
                'periodStart' => new \DateTime('2026-08-15'),
                'periodEnd' => new \DateTime('2026-09-15'),
                'total' => 325.00,
                'balanceDue' => 325.00
            ]
        ];

        return $this->render('admin/platform/invoice/index.html.twig', [
            'page' => 'invoice',
            'global_invoices_list' => $globalInvoicesList
        ]);
    } //index


    // src/Controller/Admin/Platform/PlatformInvoiceController.php

// ... (Conserve ta méthode index())

    /**
     * 34/36. SHOW : AUDIT ET VENTILATION DE LA FACTURE ET DE SES REDEVANCES PLATFORMFEE [MCD 34/36]
     */
    #[Route('/admin/platform/invoices/{id}', name: 'admin_platform_invoice_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        // 1. Extraction de l'entité Invoice principale [MCD 34]
        $invoice = [
            'id' => $id,
            'invoiceNumber' => 'INV-2026-0092',
            'agency_name' => 'Océan du Gabon',
            'type' => 'platformFee', // Facture de redevances hebdomadaires !
            'currency' => 'USD',
            'status' => 'issued',
            'issuedAt' => new \DateTime('2026-09-25'),
            'dueDate' => new \DateTime('2026-10-02'),
            'periodStart' => new \DateTime('2026-09-18'),
            'periodEnd' => new \DateTime('2026-09-25'),
            'total' => 184.00,
            'balanceDue' => 184.00
        ];

        // 2. Ventilation de toutes les lignes "36. platformFee" agrafées à cette facture [MCD 36]
        $feesBreakdown = [
            ['id' => 4501, 'sourceType' => 'reservation', 'sourceId' => 8412, 'amount' => 1.00, 'createdAt' => new \DateTime('2026-09-19 10:14:00')],
            ['id' => 4502, 'sourceType' => 'reservation', 'sourceId' => 8413, 'amount' => 1.00, 'createdAt' => new \DateTime('2026-09-19 11:22:00')],
            ['id' => 4503, 'sourceType' => 'shipment', 'sourceId' => 1102, 'amount' => 1.00, 'createdAt' => new \DateTime('2026-09-20 15:40:00')],
        ];

        return $this->render('admin/platform/invoice/show.html.twig', [
            'page' => 'invoice',
            'invoice' => $invoice,
            'fees_breakdown' => $feesBreakdown
        ]);
    } //show




}
