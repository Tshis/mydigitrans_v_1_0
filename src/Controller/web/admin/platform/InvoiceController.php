<?php

namespace App\Controller\web\admin\platform;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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


        // 4. Référentiel de ton entité "35. platformFeeRule" repris de ton MCD [MCD 35]
        $feeRulesList = [
            [
                'id' => 1,
                'sourceType' => 'reservation', // Déclencheur sur la vente de billets passagers
                'feeType' => 'fixed',          // Prélèvement à valeur fixe d'usine
                'value' => 1.00,               // Ta fameuse règle d'or à 1,00 $
                'currency' => 'USD',           // Libellé monétaire d'évaluation
                'agency_name' => null,         // Nullable = Règle générale s'appliquant à TOUT le réseau mondial
                'isActive' => true
            ],
            [
                'id' => 2,
                'sourceType' => 'shipment',    // Déclencheur sur l'expédition de colis en gare
                'feeType' => 'fixed',          // Prélèvement fixe d'usine
                'value' => 1.00,               // Règle d'or à 1,00 $ sur le fret
                'currency' => 'USD',
                'agency_name' => null,         // Règle générale globale
                'isActive' => true
            ],
            [
                'id' => 3,
                'sourceType' => 'reservation',
                'feeType' => 'percent',        // Exemple d'exception d'usine : prélèvement au pourcentage
                'value' => 2.50,               // 2.5% sur le prix du ticket
                'currency' => null,            // Nullable pour le type pourcentage comme tu l'as prévu !
                'agency_name' => 'TransKin Express', // Exception exclusive appliquée uniquement à ce partenaire
                'isActive' => false            // Règle actuellement suspendue
            ]
        ];

        return $this->render('admin/platform/invoice/index.html.twig', [
            'page' => 'invoice',
            'global_invoices_list' => $globalInvoicesList,
            'fee_rules_list' => $feeRulesList // Injection de la collection pour ton 3ème Onglet !
        ]);
    } //index



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
            'fees_price' => 184.00          // Quote-part facturation d'escales
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


    /**
     * 35. ADD FEE RULE : CRÉATION D'UN BARÈME DE PRÉLÈVEMENT SUR LE TRAFIC
     */
    #[Route('/admin/platform/fee-rules/add', name: 'admin_platform_fee_rule_add', methods: ['GET', 'POST'])]
    public function addRule(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $this->addFlash('success', 'La nouvelle règle de facturation sur trafic a été injectée avec succès.');
            return $this->redirectToRoute('admin_platform_invoice_index');
        }

        $agencies = [['id' => 1, 'name' => 'TransKin Express'], ['id' => 2, 'name' => 'Océan du Gabon']];

        return $this->render('admin/platform/invoice/fee_rule_form.html.twig', [
            'page' => 'invoice',
            'is_edit' => false,
            'agencies' => $agencies
        ]);
    }//addRule

    /**
     * 35. EDIT FEE RULE : AJUSTEMENT D'UN BARÈME EXISTANT
     */
    #[Route('/admin/platform/fee-rules/{id}/edit', name: 'admin_platform_fee_rule_edit', methods: ['GET', 'POST'])]
    public function editRule(int $id, Request $request): Response
    {
        $rule = ['id' => $id, 'sourceType' => 'reservation', 'feeType' => 'fixed', 'value' => 1.00, 'currency' => 'USD', 'agency_id' => null];
        $agencies = [['id' => 1, 'name' => 'TransKin Express'], ['id' => 2, 'name' => 'Océan du Gabon']];

        if ($request->isMethod('POST')) {
            $this->addFlash('success', 'Le barème de redevance technique a été mis à jour.');
            return $this->redirectToRoute('admin_platform_invoice_index');
        }

        return $this->render('admin/platform/invoice/fee_rule_form.html.twig', [
            'page' => 'invoice',
            'is_edit' => true,
            'rule' => $rule,
            'agencies' => $agencies
        ]);
    }//editRule

    /**
     * 35. TOGGLE FEE RULE : INTERRUPTEUR ON / OFF DE LA RÈGLE
     */
    #[Route('/admin/platform/fee-rules/{id}/toggle', name: 'admin_platform_fee_rule_toggle', methods: ['POST'])]
    public function toggleRule(int $id): Response
    {
        $this->addFlash('success', 'La validité opérationnelle du barème a été commutée.');
        return $this->redirectToRoute('admin_platform_invoice_index');
    } //toggleRule

}
