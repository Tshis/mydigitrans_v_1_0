<?php

namespace App\Controller\web\public;


use App\Dto\public\line\BookingData;
use App\Dto\public\line\LineSearchData;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Exception\BookingException;
use App\Form\BookingType;
use App\Form\LineSearchType;
use App\Repository\TripRepository;
use App\Service\BookingService;
use App\Service\BusLayoutGridBuilder;
use App\Service\LineSearcher;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
     * TUNNEL RESERVATION ÉTAPE 1 : Coordonnées du passager & sièges
     *
     * - Ouverture (depuis la page des résultats, en POST ou en GET) : departure_stop_id, arrival_stop_id
     *   et passengers_count servent à préparer le formulaire.
     * - Soumission du formulaire (champ « booking ») : crée la réservation « pending », retient les sièges
     *   (TripSeat « held ») puis redirige vers l'étape 2.
     */
    #[Route('/public/lines/trip/{slug}/booking', name: 'public_lines_booking', methods: ['GET', 'POST'])]
    public function booking(
        string $slug,
        Request $request,
        TripRepository $tripRepo,
        BookingService $booking,
        BusLayoutGridBuilder $busLayoutGridBuilder
    ): Response {
        $trip = $tripRepo->findOneBySlugWithLayout($slug);

        if (!$trip) {
            $this->addFlash('danger', 'Voyage introuvable.');
            return $this->redirectToRoute('public_lines_index');
        }

        $now = new \DateTimeImmutable();

        try {
            $booking->assertBookable($trip, $now);
        } catch (BookingException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('public_lines_index');
        }

        $data = new BookingData();

        if (!$request->request->has('booking')) {
            // Ouverture de l'étape 1 : arrêts choisis et nombre de places viennent de la page des résultats.
            $data->departureStop = $this->input($request, 'departure_stop_id');
            $data->arrivalStop = $this->input($request, 'arrival_stop_id');
            $count = max(1, min(BookingData::MAX_SEATS, (int) ($this->input($request, 'passengers_count') ?? 1)));
            $data->seats = array_fill(0, $count, null);

            $user = $this->getUser();
            if ($user instanceof User) {
                $data->fullName = $user->getFullName();
                $data->phone = $user->getPhone();
            }
        }

        $form = $this->createForm(BookingType::class, $data);
        $form->handleRequest($request);

        // Les arrêts sont vérifiés côté serveur : ils doivent appartenir à la route de ce voyage, dans le bon ordre.
        try {
            [$departureStop, $arrivalStop] = $booking->resolveSegment($trip, $data->departureStop, $data->arrivalStop);
        } catch (BookingException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('public_lines_index');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reservation = $booking->createPendingReservation($trip, $departureStop, $arrivalStop, $data, $this->getUser(), $now);

                return $this->redirectToRoute('public_lines_booking_step_2', ['reference' => $reservation->getReference()]);
            } catch (BookingException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        // Le prix vient de la base (Fare), jamais du navigateur.
        $quote = $booking->quote($trip, $departureStop, $arrivalStop, \App\Enum\SeatType::Normal, $now);

        $tripView = [
            'id' => $trip->getId(),
            'agency_name' => $trip->getRoute()?->getAgency()?->getName() ?? 'Agence Indéfini',
            'departure' => $departureStop->getCity(),
            'destination' => $arrivalStop->getCity(),
            'date' => $trip->getDepartureDate(),
            'departure_time' => $booking->departureAt($trip, $departureStop),
            'price' => $quote['amount'] ?? null,
            'currency_code' => $quote ? $quote['currency']->getCode() : '',
        ];

        $busLayout = ($trip->getBus()->getBusLayout())->toArray();

        return $this->render('public/lines/booking.html.twig', [
            'page' => 'lines',
            'form' => $form->createView(),
            'trip' => $tripView,
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
            // État et prix de chaque siège, indexés par libellé : available, held, reserved ou blocked.
            'seat_info' => $booking->seatInfo($trip, $departureStop, $arrivalStop, $now),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    } //booking


    /**
     * TUNNEL RESERVATION ÉTAPE 2 : SÉLECTION DU MODE DE RÈGLEMENT
     */
    #[Route('/public/lines/booking/{reference}/step-2', name: 'public_lines_booking_step_2', methods: ['GET'])]
    public function step_2(string $reference, BookingService $booking): Response
    {
        $now = new \DateTimeImmutable();

        try {
            $reservation = $booking->findPendingReservation($reference, $now);
        } catch (BookingException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('public_lines_index');
        }

        return $this->render('public/lines/booking_step_2.html.twig', [
            'page' => 'lines',
            'booking' => $booking->summary($reservation),
            // Échéance du bon de réservation (départ moins le délai de la route), null si le départ est trop proche.
            'station_deadline' => $booking->stationDeadline($reservation, $now),
            // Version 2 : passer à true quand le paiement direct (Mobile Money) sera branché.
            'online_payment_enabled' => false,
        ]);
    } //step_2

    /**
     * STEP 3 : FINALISATION DU TRAJET : ROUTAGE SELON LE MODE CHOISI
     */
    #[Route('/public/lines/booking/{reference}/finaliser', name: 'public_lines_booking_finalize', methods: ['POST'])]
    public function finalize(string $reference, Request $request, BookingService $booking): Response
    {
        if (!$this->isCsrfTokenValid('finalize' . $reference, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        $now = new \DateTimeImmutable();

        try {
            $reservation = $booking->findPendingReservation($reference, $now);
            $method = (string) $request->request->get('payment_method');

            if ($method === 'station_cash') {
                $booking->confirmStationPayment($reservation, $now);
                $deadline = $reservation->getExpiresAt();

                $this->addFlash('warning', sprintf(
                    'Votre réservation au guichet a été enregistrée. Veuillez la régler avant le %s.',
                    $deadline?->format('d/m/Y à H:i')
                ));

                // Route Symfony d'impression Dompdf du bon de réservation
                return $this->redirectToRoute('public_reservation_print_voucher', ['reference' => $reference]);
            }

            if ($method === 'mobile_money') {
                // Version 2 : paiement direct. À brancher ici : initier le paiement auprès de l'opérateur,
                // puis, à la confirmation, passer la réservation à « paid » et créer le Ticket.
                throw new BookingException('Le paiement en ligne sera bientôt disponible. Choisissez le règlement au guichet.');
            }

            throw new BookingException('Choisissez un mode de règlement.');
        } catch (BookingException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('public_lines_booking_step_2', ['reference' => $reference]);
        }
    } //finalize


    /**
     * STEP 4 : PRINTING VOUCHER OR TICKET IF PAYMENT DONE
     */


    /**
     * RESERVATION PDF 4.1 : COMPILATION DU BON TEMPORAIRE VIA DOMPDF
     */
    #[Route('/lines/reservations/{reference}/print-voucher', name: 'public_reservation_print_voucher', methods: ['GET'])]
    public function printVoucherPdf(string $reference, BookingService $booking): Response
    {
        $entity = $booking->findReservation($reference);

        if (!$entity || !in_array($entity->getStatus(), [ReservationStatus::Pending, ReservationStatus::Paid], true)) {
            $this->addFlash('danger', 'Ce bon de réservation n\'est plus valable.');
            return $this->redirectToRoute('public_lines_index');
        }

        // 1. Données réelles de la réservation, avec les clés attendues par voucher.html.twig
        $summary = $booking->summary($entity);

        $reservation = [
            'id' => $summary->id,
            'reference' => $summary->reference,
            'code' => $summary->reference,
            'trip_code' => $summary->tripCode,
            'route_label' => $summary->routeLabel,
            'departure_stop' => $summary->departureCity,
            'arrival_stop' => $summary->arrivalCity,
            'passenger_name' => $summary->passengerName,
            'passenger_phone' => $summary->passengerPhone,
            'seat_number' => implode(', ', $summary->seats),
            'seats' => $summary->seats,
            'fare_category' => $summary->fareCategory,
            'amount' => $summary->total,
            'currency_code' => $summary->currencyCode,
            'departure_at' => $summary->departureAt,
            'expires_at' => $summary->expiresAt,
        ];

        // 2. Configuration stricte des options d'usine Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true); // Autorise impérativement le chargement de FontAwesome !

        $dompdf = new Dompdf($pdfOptions);

        // 3. Rendu du code HTML Twig en mémoire morte
        $html = $this->renderView('public/lines/voucher.html.twig', [
            'agency_name' => $summary->agencyName,
            'reservation' => $reservation,
            'is_pdf_mode' => true
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4. Capture du flux binaire et retour via Symfony Response
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
     * Version 2 : à brancher sur un vrai Ticket (réservation payée) quand le paiement direct existera.
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


    /**
     * Lit un champ envoyé par la page des résultats, en POST ou en GET.
     */
    private function input(Request $request, string $key): ?string
    {
        $value = $request->request->get($key) ?? $request->query->get($key);

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }
}
