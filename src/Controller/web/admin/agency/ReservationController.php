<?php

namespace App\Controller\web\admin\agency;

use App\Service\BusLayoutGridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{

    #[Route('/admin/agency/reservations', name: 'admin_agency_reservation_index')]
    public function index(Request $request): Response
    {
        return $this->render('admin/agency/reservation/index.html.twig', [
            'page' => 'reservation',
        ]);
    } //index


    #[Route('/admin/agency/reservation/add/{code}', name: 'admin_agency_reservation_add')]
    public function add(Request $request, BusLayoutGridBuilder $busLayoutGridBuilder, string $code): Response
    {
        $session = $request->getSession();
        $layouts = $session->get('bus_layout', []);

        // Simulation : l'ID du layout dépend du code du voyage / véhicule
        $layoutId = 1;
        $busLayout = $layouts[$layoutId] ?? null;

        if (!$busLayout) {
            throw $this->createNotFoundException('Bus layout introuvable');
        }

        // --- SIMULATION DES TARIFS PROVENANT DU MCD ---

        // La devise peut changer dynamiquement selon le trajet ou la session d'agence
        $currencyCode = 'CDF'; // Exemples : 'CDF', 'USD', 'XAF'

        // Grille tarifaire par segment : "id_depart-id_arrivee" => Prix en CDF
        $pricingMatrix = [
            '1-3' => 45000, // Kinshasa -> Kikwit (Complet)
            '1-2' => 25000, // Kinshasa -> Kenge (Partiel)
            '2-3' => 30000  // Kenge -> Kikwit (Partiel)
        ];

        // Prix par catégorie de place
        $fareCategories = [
            'seat' => 0,      // Pas de surplus pour un siège Standard
            'vip'  => 15000   // +15 000 CDF de majoration pour la classe VIP
        ];


        if ($request->isMethod('POST')) {
            $reference = "RSV-01-2026";
            return $this->printVoucherPdf($reference);
        }

        return $this->render('admin/agency/reservation/add.html.twig', [
            'page' => 'reservation',
            'trip_code' => $code,
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
            'pricing_matrix' => $pricingMatrix,
            'fare_categories' => $fareCategories,
            'currency_code' => $currencyCode,
        ]);
    } //add

    #[Route('/admin/agency/reservation/{reference}', name: 'admin_agency_reservation_show')]
    public function show(Request $request): Response
    {
        return $this->render('admin/agency/reservation/show.html.twig', [
            'page' => 'reservation',
        ]);
    } //show

    /**
     * RESERVATION PDF : COMPILATION DU BON TEMPORAIRE VIA DOMPDF
     */
    #[Route('/admin/agency/reservations/{reference}/print-pdf', name: 'admin_agency_reservation_print_voucher', methods: ['GET'])]
    public function printVoucherPdf(string $reference): Response
    {
        // 1. Modélisation et extraction de ton entité de Réservation en cours
        $reservation = [
            'id' => 1,
            'reference' => $reference,
            'code' => 'BKG-2026-0914-A8X',
            'trip_code' => 'TRP-2026-0042',
            'route_label' => 'Kinshasa - Kikwit via Kenge',
            'departure_stop' => 'Kinshasa (Gare Centrale)',
            'arrival_stop' => 'Kikwit (Terminus)',
            'passenger_name' => 'Kabila Dieudonné',
            'passenger_phone' => '+243 812 345 678',
            'seat_number' => '01',
            'fare_category' => 'VIP',
            'amount' => 45.00,
            'currency_code' => 'USD'
        ];

        // 2. Configuration stricte des options d'usine Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true); // Autorise impérativement le chargement de FontAwesome !

        $dompdf = new Dompdf($pdfOptions);

        // 3. Rendu du code HTML Twig en mémoire morte
        $html = $this->renderView('admin/agency/reservation/print_bon.html.twig', [
            'agency_name' => 'TransKin Express', // Lié au context de l'utilisateur connecté
            'reservation' => $reservation,
            'is_pdf_mode' => true
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4. Capture du flux binaire binaire et retour via Symfony Response
        $pdfOutput = $dompdf->output();
        $filename = sprintf('Bon_Reservation_%s.pdf', $reservation['code']);

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename)
        ]);
    } //printVoucherPdf
}
