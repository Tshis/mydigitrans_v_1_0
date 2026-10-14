<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;

class ShipmentController extends AbstractController
{

    #[Route('/admin/agency/shipments', name: 'admin_agency_shipment_index')]
    public function index(Request $request): Response
    {
        return $this->render('admin/agency/shipment/index.html.twig', [
            'page' => 'shipment',
        ]);
    } //index


    #[Route('/admin/agency/shipment/add', name: 'admin_agency_shipment_add')]
    public function add(Request $request): Response
    {


        // Configuration par défaut de l'agence connectée
        $systemCurrency = 'USD';
        $pricePerKg = 1.25; // 1.25 $ par Kilo pour la ligne active

        $currentBranch = [
            'id' => 1,
            'name' => 'Kinshasa (Gare Centrale Siège)'
        ];

        // Succursales de destination du réseau (MCD Table 4)
        $branches = [
            ['id' => 2, 'name' => 'Succursale Goma'],
            ['id' => 3, 'name' => 'Succursale Matadi'],
            ['id' => 4, 'name' => 'Succursale Kikwit Terminus'],
        ];

        // Liste des devises actives pour fixer manuellement le prix par pièce
        $activeCurrencies = [
            ['code' => 'USD'],
            ['code' => 'CDF'],
        ];

        if ($request->isMethod('POST')) {


            return $this->redirectToRoute('admin_agency_shipment_print', ['reference' => 'SHP-2026-001']);
        }



        return $this->render('admin/agency/shipment/add.html.twig', [
            'page' => 'shipment',
            'system_currency'   => $systemCurrency,
            'price_per_kg'      => $pricePerKg,
            'current_branch'    => $currentBranch,
            'branches'          => $branches,
            'active_currencies' => $activeCurrencies,

        ]);
    } //add



    #[Route('/admin/agency/shipment/{reference}', name: 'admin_agency_shipment_show')]
    public function show(Request $request): Response
    {
        return $this->render('admin/agency/shipment/show.html.twig', [
            'page' => 'shipment',
        ]);
    } //show



    /**
     * GESTION DU FRET : IMPRESSION DES BONS AVEC SÉCURITÉ ANTI-FRAUDE DUPLICATA
     */
    #[Route('/admin/agency/shipment/print/{reference}', name: 'admin_agency_shipment_print', methods: ['GET'])]
    public function printFretVouchers(string $reference, Request $request): Response
    {
        // 1. Simulation de ton entité Colis/Fret
        $package = [
            'id' => 512,
            'reference' => $reference, // Consomme dynamiquement la référence de l'URL
            'waybill' => 'AWB-202610-F892',
            'pickup_code' => 'MDT-591A',
            'sender_name' => 'Daniel Mukendi',
            'sender_phone' => '+243 812 345 678',
            'receiver_name' => 'Merveille Kabamba',
            'receiver_phone' => '+243 998 765 432',
            'route' => 'Kinshasa ➔ Kikwit',
            'arrival_branch' => 'Gare de Kikwit (Terminus)',
            'nature' => 'Carton de marchandises',
            'weight' => 28.5,
            'amount_paid' => 35.00,
            'currency' => 'USD',
            'created_at' => new \DateTime('now')
        ];

        // 2. 🚨 LE BLINDAGE SÉCURITÉ : Calcul de l'écart de temps en arrière-plan
        $now = new \DateTime('now');
        $interval = $now->diff($package['created_at']);

        // Convertit l'écart en minutes totales
        $minutesSinceCreation = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        // Si le colis a été créé il y a MOINS de 15 minutes, on autorise l'impression initiale du doublé.
        // Au-delà de 15 minutes, le système passe d'office en mode 'duplicate' restrictif.
        $printMode = ($minutesSinceCreation <= 15) ? 'creation' : 'duplicate';

        // 3. Génération locale et immuable du QR Code v5 avec ta structure immuable validée
        $qrBase64 = null;
        if ($printMode === 'creation') {
            $qrCode = new QrCode(
                data: 'MDT-FRET-VERIFY-' . $package['waybill'],
                encoding: new Encoding('UTF-8'),
                size: 150,
                margin: 0
            );
            $writer = new PngWriter();
            try {
                $result = $writer->write($qrCode);
                $qrBase64 = $result->getDataUri();
            } catch (\Exception $e) {
                $qrBase64 = null;
            }
        }

        // 4. Initialisation de la carrosserie Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('admin/agency/shipment/fret_vouchers_pdf.html.twig', [
            'agency_name' => 'TransKin Express',
            'package' => $package,
            'print_mode' => $printMode,
            'qr_base64' => $qrBase64
        ]);

        $dompdf->loadHtml($html);

        // Calibration dynamique de la hauteur de la bande thermique 80mm
        $paperHeight = ($printMode === 'creation') ? 793.70 : 425.20;
        $dompdf->setPaper([0, 0, 250.00, $paperHeight], 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Pieces_Fret.pdf"'
        ]);
    }//printFretVouchers    


    /**
     * GUICHET ARRIVÉE : AFFICHAGE DE L'ÉCRAN DE RETRAIT DE COLIS
     */
    #[Route('/admin/agency/shipment/{reference}/pickup', name: 'admin_agency_shipment_pickup', methods: ['GET'])]
    public function pickupForm(string $reference): Response
    {
        // Extraction de ton entité Colis en attente à la gare de destination [Profile]
        $package = [
            'id' => 412,
            'reference' => $reference,
            'waybill' => $reference,
            'sender_name' => 'Daniel Mukendi',
            'receiver_name' => 'Merveille Kabamba',
            'receiver_phone' => '+243 998 765 432',
            'arrival_branch' => 'Gare de Kikwit (Terminus)',
            'nature' => 'Carton de produits pharmaceutiques',
            'weight' => 28.5
        ];

        return $this->render('admin/agency/shipment/pickup.html.twig', [
            'page' => 'shipment',
            'package' => $package
        ]);
    }//pickupForm

    /**
     * TRAITEMENT DU DÉBLOCAGE : COMPARAISON DU CODE ET ENREGISTREMENT DE LA DÉCHARGE JURIDIQUE
     */
    #[Route('/admin/agency/shipment/{reference}/pickup/process', name: 'admin_agency_shipment_pickup_process', methods: ['POST'])]
    public function processPickup(string $reference, Request $request): Response
    {
        // 1. Récupération du colis en BDD (Ici simulation de son code secret d'usine d'origine)
        $truePickupCode = '591A';

        // 2. Capture des entrées du guichetier d'arrivée [Profile]
        $inputCode = strtoupper(trim($request->request->get('input_pickup_code')));
        $idType = $request->request->get('receiver_id_type');
        $idNumber = trim($request->request->get('receiver_id_number'));

        // 3. BARRIÈRE 1 : Validation stricte du code d'usine [Profile]
        if ($inputCode !== $truePickupCode) {
            $this->addFlash('danger', 'Erreur de déblocage : Le code secret de retrait dicté par le destinataire est incorrect.');
            return $this->redirectToRoute('admin_agency_shipment_pickup', ['reference' => $reference]);
        }

        // 4. ACTION PRODUCTION :
        // - Passage du statut du colis à 'delivered' (livré)
        // - Enregistrement des champs audit de ton MCD : receiver_id_type et receiver_id_number [Profile]
        // - Horodatage de l'opération comptable finale

        $this->addFlash('success', 'Livraison approuvée. La décharge juridique a été enregistrée avec succès.');
        return $this->redirectToRoute('admin_agency_shipment_index');
    } //processPickup



}
