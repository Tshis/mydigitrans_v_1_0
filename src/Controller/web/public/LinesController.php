<?php

namespace App\Controller\web\public;

use App\Dto\public\line\LineSearchData;
use App\Entity\RouteStop;
use App\Enum\FareTargetType;
use App\Form\LineSearchType;
use App\Repository\TripRepository;
use App\Service\BusLayoutGridBuilder;
use App\Service\LineSearcher;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(private readonly EntityManagerInterface $em) {}

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
    public function booking(
        string $slug,
        Request $request,
        TripRepository $tripRepo,
        LineSearcher $lineSearcher,
        BusLayoutGridBuilder $busLayoutGridBuilder
    ): Response {


        if ($request->isMethod('post')) {

            //enregistrement de la reservation avec le status pending & imprimer


            $reservation = [
                'reference' => 'RSV-001-2026',
                //.......
            ];



            //redirection vers la 2e etape Lorsque le paiement en ligne sera dispo
            // return $this->redirectToRoute('public_lines_booking_step_2', ['reference' => $reservation['reference']]);
        }

        // ◄ CORRECTION : On récupère le Trip en BDD grâce au slug de l'URL
        $trip = $tripRepo->findOneBySlugWithLayout($slug);

        if (!$trip) {
            $this->addFlash('danger', 'Voyage introuvable.');
            return $this->redirectToRoute('public_lines_index');
        }

        $busLayout = ($trip->getBus()->getBusLayout())->toArray();

        // ◄ SÉCURISATION : Chargement des entités réelles depuis les IDs du formulaire
        $departureStopId = $request->request->get('departure_stop_id');
        $arrivalStopId = $request->request->get('arrival_stop_id');

        $departureStop = $this->em->getRepository(RouteStop::class)->find((int)$departureStopId);
        $arrivalStop = $this->em->getRepository(RouteStop::class)->find((int)$arrivalStopId);

        // Sécurité supplémentaire : On vérifie que ces arrêts appartiennent bien à la route du bus !
        if (!$departureStop || !$arrivalStop || $departureStop->getRoute() !== $trip->getRoute()) {
            $this->addFlash('danger', 'Une erreur de sécurité est survenue sur l\'itinéraire.');
            return $this->redirectToRoute('public_lines_index');
        }

        // 1. EXTRACTION DE L'AGENCE (MCD 19)
        $agency = $trip->getRoute()?->getAgency();

        // 2. ◄ RÉCUPÉRATION DU TARIF VIA LE RECOURS À TON SERVICE DE GRILLE (MCD 21)
        // On charge les tarifs liés à la route de l'agence pour l'associer à l'algorithme
        $fares = $this->em->getRepository(\App\Entity\Fare::class)->findBy([
            'targetType' => FareTargetType::Route,
            'targetId' => $trip->getRoute()->getId(),
            'isActive' => true
        ]);

        // On appelle directement pickFare de ton LineSearcher qui est maintenant accessible !
        $fareEntity = $lineSearcher->pickFare($fares, $departureStop->getId(), $arrivalStop->getId());
        $ticketPrice = $fareEntity?->getAmount() ?? 0.0;
        $ticketCurrency = $fareEntity?->getCurrency()->getCode();


        //3. Recuperation de la bonne heure
        // 3.1. Initialisation de l'heure brute du point de départ initial (ex: 08:00)
        $baseDate = \DateTimeImmutable::createFromInterface($trip->getDepartureDate());
        $departureTime = $trip->getDepartureTime();

        $origin = $baseDate->setTime(
            (int) $departureTime->format('H'),
            (int) $departureTime->format('i'),
            0
        );

        // 3.2. ◄ CALCUL DE L'HEURE REELLE PAR INTERVALLE (ex: 08h00 + 120 min = 10h00)
        // On récupère les minutes de décalage stockées dans l'arrêt de départ sécurisé
        $departureOffset = (int) $departureStop->getDurationFromDeparture(); //
        $realDepartureAt = $origin->add(new \DateInterval(sprintf('PT%dM', $departureOffset)));

        // 4. Intégration sécurisée dans ton tableau $trip destiné à Twig :
        $trip = [
            'id' => $trip->getId(),
            'agency_name' => $agency?->getName() ?? 'Agence Indéfini',
            'departure' => $departureStop->getCity(),
            'destination' => $arrivalStop->getCity(),
            'date' => $trip->getDepartureDate(),
            'departure_time' =>  $realDepartureAt,

            // ◄ SÉCURISATION DES DONNÉES FINANCIÈRES (Tirées de l'ORM, impossibles à falsifier)
            /* 'price' => $ticketPrice,
            'currency_code' => $ticketCurrency */
            'price' => $ticketPrice, // Prix exact et certifié recalculé en arrière-plan
            'currency_code' => $ticketCurrency ?? 'CDF'
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
