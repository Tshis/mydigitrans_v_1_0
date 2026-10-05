<?php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

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


    /**
     * 34. PRINT : ÉMISSION VISUELLE ET PRÉPARATION DU FLUX PDF/IMPRANTE [MCD 34]
     */
    #[Route('/admin/platform/invoices/{id}/print', name: 'admin_platform_invoice_print', methods: ['GET'])]
    public function printReceipt(int $id): Response
    {
        // Extraction de la facture d'agence (Simulation ORM complète) [MCD 34]
        $invoice = [
            'id' => $id,
            'invoiceNumber' => 'INV-2026-0092',
            'agency_name' => 'Océan du Gabon',
            'registrationNumber' => 'RCCM-LBV-2025-M12A',
            'taxNumber' => 'IF-GAB-9941-X',
            'type' => 'mixed', // Facture mixte : Forfait + Commissions d'escales !
            'plan_name' => 'Premium Multitenant',
            'currency' => 'USD',
            'status' => 'paid', // Statut payé validé
            'issuedAt' => new \DateTime('2026-09-25'),
            'dueDate' => new \DateTime('2026-10-02'),
            'periodStart' => new \DateTime('2026-09-18'),
            'periodEnd' => new \DateTime('2026-09-25'),
            'subtotal' => 434.00,
            'discountTotal' => 0.00,
            'total' => 434.00,
            'balanceDue' => 0.00,
            'subscription_price' => 250.00, // Quote-part forfait fixe
            'total_items_count' => 184,     // Nombre de billets vendus à 1$
            'fees_price' => 184.00          // Quote-part commissions d'escales
        ];

        return $this->render('admin/platform/invoice/print.html.twig', [
            'page' => 'invoice',
            'invoice' => $invoice
        ]);
    } //printReceipt

    /**
     * 34. EXPORT PDF : GÉNÉRATION ET TÉLÉCHARGEMENT COMPTABLE VIA DOMPDF [MCD 34]
     */
    #[Route('/admin/platform/invoices/{id}/pdf', name: 'admin_platform_invoice_pdf', methods: ['GET'])]
    public function generatePdf(int $id): Response
    {
        $invoice = [
            'id' => $id,
            'invoiceNumber' => 'INV-2026-0092',
            'agency_name' => 'Océan du Gabon',
            'registrationNumber' => 'RCCM-LBV-2025-M12A',
            'taxNumber' => 'IF-GAB-9941-X',
            'type' => 'mixed',
            'plan_name' => 'Premium Multitenant',
            'currency' => 'USD',
            'status' => 'paid',
            'issuedAt' => new \DateTime('2026-09-25'),
            'dueDate' => new \DateTime('2026-10-02'),
            'periodStart' => new \DateTime('2026-09-18'),
            'periodEnd' => new \DateTime('2026-09-25'),
            'subtotal' => 434.00,
            'discountTotal' => 0.00,
            'total' => 434.00,
            'balanceDue' => 0.00,
            'subscription_price' => 250.00,
            'total_items_count' => 184,
            'fees_price' => 184.00
        ];

        // 🚨 ENCODAGE DU LOGO EN BASE64 POUR SÉCURISER DOMPDF
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/img-core/logo.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('admin/platform/invoice/print.html.twig', [
            'invoice' => $invoice,
            'logo_base64' => $logoBase64, // On injecte le logo converti !
            'is_pdf_mode' => true
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $filename = sprintf('Facture_%s.pdf', $invoice['invoiceNumber']);

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename)
        ]);
    } //generatePdf
}
