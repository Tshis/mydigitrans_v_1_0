<?php

namespace App\Controller\web\public;

use App\Dto\public\line\LineSearchData;
use App\Form\LineSearchType;
use App\Service\BusLayoutGridBuilder;
use App\Service\LineSearcher;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LinesController extends AbstractController
{
    #[Route('/public/lines', name: 'public_lines_index')]
    public function index(): Response
    {
        return $this->render('public/lines/index.html.twig', [
            'page' => 'lines',
        ]);
    } //index


    /**
     * SÉQUENCE INTERACTION : TRAITEMENT DE LA RECHERCHE ET RENDU DES RÉSULTATS DISPONIBLES 
     */
    #[Route('/public/lines/results', name: 'public_lines_results')]
    public function results(Request $request, LineSearcher $lineSearcher): Response
    {
        $form = $this->createForm(LineSearchType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('public_lines_index');
        }

        if (!$form->isValid()) {
            return $this->render('public/lines/index.html.twig', [
                'page' => 'lignes',
                'searchForm' => $form->createView(),
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        /** @var LineSearchData $criteria */
        $criteria = $form->getData();

        $results = $lineSearcher->search($criteria);

        // Extraction des noms d'agences uniques présents dans les résultats
        $uniqueAgencies = array_unique(array_map(
            static fn($item) => $item->agencyName,
            $results
        ));


        return $this->render('public/lines/results.html.twig', [
            'page' => 'lines',
            'criteria' => $criteria,
            'results' => $results,
            'agencies' => $uniqueAgencies,
            'searchForm' => $form->createView(),
        ]);
    } //results




    /**
     * TUNNEL RESERVATION ÉTAPE 1 : Coordonnées du Passager & Siège
     */
    #[Route('/public/lines/trip/{slug}/booking', name: 'public_lines_booking')]
    public function booking(string $slug, Request $request, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {

        $busLayout = [
            "id" => 1,
            "name" => "Yutong 50 places modele 3-2",
            "agency" => null,
            "rows" => 11,
            "columns" => 4,
            "aisles" => [0 => 2],
            "specialPositions" => [
                [
                    "type" => "driver",
                    "row" => 1,
                    "col" => 1
                ],
                [
                    "type" => "aisle",
                    "row" => 1,
                    "col" => 2
                ],
                [
                    "type" => "door",
                    "row" => 2,
                    "col" => 4
                ],
                [
                    "type" => "aisle",
                    "row" => 2,
                    "col" => 3
                ]
            ],
            "hasBackExtraSeat" => true,
            "isValided" => false,
            "createdAt" => "2026-10-07 16:52:1",
            "resolvedAt" => null,
        ];

        if ($request->isMethod('post')) {

            //enregistrement de la reservation avec le status pending & imprimer


            $reservation = [
                'reference' => 'RSV-001-2026',
                //.......
            ];



            //redirection vers la 2e etape Lorsque le paiement en ligne sera dispo
            // return $this->redirectToRoute('public_lines_booking_step_2', ['reference' => $reservation['reference']]);
        }


        // Extraction du voyage concerné [Profile]
        $trip = [
            'id' => 101,
            'agency_name' => 'TransKin Express',
            'departure' => 'Kinshasa',
            'destination' => 'Kikwit',
            'date' => new \DateTime('+2 days'),
            'departure_time' => '14h30',
            'price' => 45000,
            'currency_code' => 'CDF'
        ];

        return $this->render('public/lines/booking.html.twig', [
            'page' => 'lines',
            'trip' => $trip,
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
        ]);
    } //booking


    /**
     * TUNNEL RESERVATION ÉTAPE 2 : SÉLECTION DU MODE DE RÈGLEMENT
     */
    #[Route('/public/lines/booking/{reference}/step-2', name: 'public_lines_booking_step_2')]
    public function step_2(string $reference): Response
    {
        $booking = [
            'id' => 112,
            'reference' => 'CODE-TRIP-001',
            'passenger_name' => 'Kabila Dieudonné',
            'departure' => 'Kinshasa',
            'destination' => 'Kikwit',
            'bus_model' => 'Mercedes Marcopolo (VIP)',
            'seat_number' => '01',
            'price' => 45.00,
            'currency_code' => 'USD'
        ];

        return $this->render('public/lines/booking_step_2.html.twig', [
            'page' => 'lines',
            'booking' => $booking
        ]);
    }//step_2

    /**
     * STEP 3 : FINALISATION DU TRAJET : ROUTAGE SELON LE MODE CHOISI
     */
    #[Route('/public/lines/booking/{reference}/finaliser', name: 'public_lines_booking_finalize')]
    public function finalize(Request $request): Response
    {
        $method = $request->request->get('payment_method');

        if ($method === 'station_cash') {
            $this->addFlash('warning', 'Votre réservation au guichet a été enregistrée. Veuillez la régler sous 2 heures.');
            // Route Symfony d'impression Dompdf du bon de réservation que nous avons codé ensemble
            return $this->redirectToRoute('public_reservation_print_voucher', ['reference' => 'RSV-2026-001']);
        }

        // Sinon : Routage vers l'API de l'opérateur Mobile Money sélectionné
        $this->addFlash('success', 'Billet électronique payé et validé avec succès.');
        return $this->redirectToRoute('public_lines_ticket_download', ['reference' => 'RSV-2026-001']);
    } //finalize


    /**
     * STEP 4 : PRINTING VOUCHER OR TICKET IF PAYMENT DONE
     */


    /**
     * RESERVATION PDF 4.1 : COMPILATION DU BON TEMPORAIRE VIA DOMPDF
     */
    #[Route('/lines/reservations/{reference}/print-voucher', name: 'public_reservation_print_voucher', methods: ['GET'])]
    public function printVoucherPdf(string $reference): Response
    {
        // 1. Modélisation et extraction de ton entité de Réservation en cours
        $reservation = [
            'id' => 1,
            'reference' => $reference,
            'code' => $reference,
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
        $html = $this->renderView('public/lines/voucher.html.twig', [
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


    /**
     * RESERVATION PDF 4.2 : COMPILATION DU TICKER VIA DOMPDF
     */


    /**
     * BILLET COMPTANT PUBLIC : GÉNÉRATION ET TÉLÉCHARGEMENT DU BILLET SÉCURISÉ A4 [Profile]
     */
    #[Route('/lines/ticket/{reference}/telecharger', name: 'public_lines_ticket_download')]
    public function downloadPublicTicket(string $reference): Response
    {
        // 1. Extraction de la réservation payée de ton entité [Profile]
        $ticket = [
            'id' => 212,
            'number' => $reference,
            'passenger_name' => 'Jean Mukendi',
            'passenger_phone' => '+243 812 345 678',
            'paid_at' => new \DateTime('now'),
            'route' => 'Kinshasa ➔ Kikwit via Kenge',
            'departure_stop' => 'Kinshasa (Gare Centrale)',
            'arrival_stop' => 'Kikwit (Terminus)',
            'departure_date' => new \DateTime('+2 days'),
            'departure_time' => '14h30',
            'bus_plate' => '2432AB01 (Mercedes Marcopolo VIP)',
            'seat_number' => '01',
            'fare_category' => 'VIP',
            'amount' => 45.00,
            'currency_code' => 'USD',
            'security_hash' => 'MDT-SECURE-WEB-7491-KABILA'
        ];

        // 2. Génération locale et immuable du Code QR v5 [Profile]
        $qrCode = new QrCode(
            data: $ticket['security_hash'],
            encoding: new Encoding('UTF-8'),
            size: 150,
            margin: 0
        );

        $writer = new PngWriter();
        $qrBase64 = null;
        try {
            $result = $writer->write($qrCode);
            $qrBase64 = $result->getDataUri();
        } catch (\Exception $e) {
            $qrBase64 = null;
        }

        // 3. Initialisation et configuration de Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('public/lines/ticket.html.twig', [
            'agency_name' => 'TransKin Express',
            'ticket' => $ticket,
            'qr_base64' => $qrBase64
        ]);

        $dompdf->loadHtml($html);

        // 🚨 CONFIGURATION FORMAT DU PAPIER : Vrai A4 Standard International en mode Portrait
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $filename = sprintf('Billet_MyDigitrans_%s.pdf', $ticket['number']);

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename) // 'attachment' force le téléchargement direct sur le smartphone du client
        ]);
    } //downloadPublicTickets
}
