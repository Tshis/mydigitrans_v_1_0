<?php

namespace App\Controller\web\admin\agency;

use App\Dto\admin\agency\TripPlanningData;
use App\Entity\User;
use App\Exception\TripPlanningException;
use App\Form\TripPlanningType;
use App\Service\AgencyTripListing;
use App\Service\BusLayoutGridBuilder;
use App\Service\TripPlanningService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode; // Utilisation de l'objet natif direct
use Endroid\QrCode\Writer\PngWriter; // Utilisation du Writer standard
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TripController extends AbstractController
{

    /**
     * LISTE DES VOYAGES : statistiques et filtres (date exacte, ligne, état opérationnel).
     */
    #[Route('/admin/agency/trips', name: 'admin_agency_trip_index')]
    public function index(Request $request, AgencyTripListing $listing): Response
    {
        $user = $this->agencyUser();
        $agency = $user->getAgency();
        $branch = AgencyTripListing::scopeBranch($user);
        $now = new \DateTimeImmutable();

        $filters = [
            'date' => $this->parseDate($request->query->get('filter_date')),
            'route' => ctype_digit((string) $request->query->get('filter_route')) ? (int) $request->query->get('filter_route') : null,
            'status' => \array_key_exists((string) $request->query->get('filter_status'), AgencyTripListing::STATUS_FILTERS)
                ? (string) $request->query->get('filter_status')
                : null,
        ];

        return $this->render('admin/agency/trip/index.html.twig', [
            'page' => 'trip',
            'stats' => $listing->stats($agency, $branch, $now),
            'trips' => $listing->search($agency, $branch, $filters, $now),
            'route_options' => $listing->routeOptions($agency),
            'filters' => [
                'date' => $filters['date']?->format('Y-m-d') ?? '',
                'route' => $filters['route'],
                'status' => $filters['status'] ?? '',
            ],
        ]);
    } //index

    /**
     * PLANIFICATION D'UN VOYAGE : crée le voyage et, en cascade, tous les sièges du bus.
     */
    #[Route('/admin/agency/trip/add', name: 'admin_agency_trip_add', methods: ['GET', 'POST'])]
    public function add(Request $request, TripPlanningService $planner): Response
    {
        $user = $this->agencyUser();

        $data = new TripPlanningData();
        $today = new \DateTimeImmutable('today');
        $data->departureDate = $today;
        $data->departureTime = new \DateTimeImmutable('1970-01-01 06:00');
        $data->arrivalDate = $today;
        $data->arrivalTime = new \DateTimeImmutable('1970-01-01 18:00');

        $form = $this->createForm(TripPlanningType::class, $data, ['agency' => $user->getAgency()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $trip = $planner->plan($data, $user);

                $this->addFlash('success', sprintf('Le voyage %s est planifié : les places sont ouvertes à la vente.', $trip->getCode()));

                return $this->redirectToRoute('admin_agency_trip_index');
            } catch (TripPlanningException $e) {
                foreach ($e->getErrors() as $field => $message) {
                    $target = $form->has($field) ? $form->get($field) : $form;
                    $target->addError(new FormError($message));
                }
            }
        }

        return $this->render('admin/agency/trip/add.html.twig', [
            'page' => 'trip',
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    } //add



    #[Route('/admin/agency/trip/show/{code}', name: 'admin_agency_trip_show')]
    public function show(Request $request): Response
    {
        return $this->render('admin/agency/trip/show.html.twig', [
            'page' => 'trip',
        ]);
    } //show

    /**
     * Ancienne adresse de recherche : les filtres sont gérés par la liste.
     */
    #[Route('/admin/agency/trip/search/', name: 'admin_agency_trip_search')]
    public function search(Request $request): Response
    {
        return $this->redirectToRoute('admin_agency_trip_index', $request->query->all());
    } //search


    /**
     * MANIFESTE PDF : COMPILATION ET EXPORT DE LA FEUILLE DE ROUTE VIA DOMPDF
     */
    #[Route('/admin/agency/trips/{code}/manifest-pdf', name: 'admin_agency_trip_manifest_pdf', methods: ['GET'])]
    public function generateManifestPdf(string $code): Response
    {
        // 1. Extraction et compilation des caractéristiques du voyage
        $trip = [
            'id' => 1,
            'code' => $code,
            'tripNumber' => 'TRP-2026-0042',
            'route' => 'Kinshasa - Kikwit via Kenge',
            'departureDate' => new \DateTime('2026-07-14'),
            'departureTime' => '14h30',
            'arrivalTime' => '22h30',
            'bus' => 'N°01 (Mercedes Marcopolo)',
            'plate' => '2432AB01',
            'capacity' => 54,
            'driver' => 'Jean Mukendi',
            'coDriver' => 'Dummy Tshimanga',
            'statusLabel' => 'Embarquement en cours'
        ];

        // 2. Liste des passagers enregistrés (MCD 36/Reservation)
        $passengersList = [
            ['seat' => '01', 'category' => 'VIP', 'name' => 'Kabila Dieudonné', 'route_segment' => 'Kinshasa → Kikwit', 'phone' => '+243 812 345 678', 'status' => 'confirmed'],
            ['seat' => '03', 'category' => 'Std', 'name' => 'Ilunga Christian', 'route_segment' => 'Kenge → Kikwit', 'phone' => '+243 824 556 677', 'status' => 'pending']
        ];

        // 3. Options de base Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        // 4. Rendu du template Twig épuré
        $html = $this->renderView('admin/agency/trip/manifest_pdf.html.twig', [
            'agency_name' => 'TransKin Express', // Simulé via app.user.agency
            'trip' => $trip,
            'passengers_list' => $passengersList
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 5. Capture du flux et retour sécurisé Symfony Response
        $pdfOutput = $dompdf->output();
        $filename = sprintf('Manifeste_%s.pdf', $trip['tripNumber']);

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename)
        ]);
    }//generateManifestPDF

    /**
     * CAISSE GARES : AFFICHAGE DE L'ÉCRAN DE PAIEMENT SANS RÉSERVATION PRÉALABLE [MCD 36]
     */
    #[Route('/admin/agency/trips/{code}/direct-purchase', name: 'admin_agency_trip_direct_purchase', methods: ['GET'])]
    public function directPurchase(string $code, Request $request,  BusLayoutGridBuilder $busLayoutGridBuilder): Response
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

        // 1. Récupération du voyage
        $trip = ['id' => 111, 'code' => $code, 'tripNumber' => 'TRP-2026-0042', 'route' => 'Kinshasa - Kikwit', 'capacity' => 54];

        // 2. Capture du siège cliqué sur le plan (Seatmap) de l'étape d'avant
        $selectedSeat = $request->query->get('seat', '01');

        return $this->render('admin/agency/trip/direct_purchase.html.twig', [
            'page' => 'trip',
            'trip' => $trip,
            'selected_seat' => $selectedSeat,
            'fare_category' => 'VIP',
            'price' => 45.00,
            'seatmap'  => $busLayoutGridBuilder->build($busLayout),
            'currency_code' => 'USD'
        ]);
    }//directPurchase

    /**
     * TRAITEMENT DE L'ENCAISSÉ : PERSISTANCE DU BILLET DIRECT ET IMPRESSION DU TICKET
     */
    #[Route('/admin/agency/trips/{code}/direct-purchase/process', name: 'admin_agency_trip_direct_purchase_process')]
    public function processDirectPurchase(string $code, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        // EN PRODUCTION ORM :
        // 1. Création immédiate de l'entité Reservation au statut 'confirmed' (Billet d'embarquement)
        // 2. Récupération de la session tiroir-caisse de l'agent connecté
        // 3. Ajout de la somme (ex: 45$) dans la table platformFinancialOperation [MCD 45]
        // 4. Lancement direct du template d'impression du billet thermique de bord
        $newTicketId = 84; // Récupération de l'ID généré en BDD

        // 🚨 REDIRECTION DIRECTE VERS L'IMPRESSION DU TICKET THERMIQUE
        return $this->redirectToRoute('admin_agency_trip_ticket_print', ['id' => $newTicketId]);
    } //processDirectPurchase


    /**
     * IMPRESSION TICKET : SORTIE THERMIQUE DU TICKET D'ACCÈS AU BUS VIA DOMPDF
     */
    #[Route('/admin/agency/trips/ticket/{id}/print', name: 'admin_agency_trip_ticket_print')]
    public function printTicketPdf(int $id): Response
    {
        $ticket = [
            'id' => $id,
            'number' => 'TKT-202610-0084',
            'passenger_name' => 'Jean Mukendi',
            'passenger_phone' => '+243 812 345 678',
            'route' => 'Kinshasa ➔ Kikwit via Kenge',
            'departure_stop' => 'Kinshasa (Gare Centrale)',
            'arrival_stop' => 'Kikwit (Terminus)',
            'departure_date' => new \DateTime('now'),
            'departure_time' => '14h30',
            'bus_plate' => '2432AB01 (Yutong)',
            'seat_number' => '05',
            'fare_category' => 'Standard',
            'amount' => 45.00,
            'currency_code' => 'USD',
            'security_hash' => 'MDT-TKT-202610-0888-SECURE'
        ];

        // 🚨 NETTOYAGE : Configuration moderne sans setters obsolètes
        // On passe les paramètres directement à l'instanciation pour effacer les lignes soulignées
        $qrCode = new QrCode(
            data: $ticket['security_hash'],
            encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
            size: 150,
            margin: 0
        );

        $writer = new PngWriter();
        $qrBase64 = null;

        try {
            // Syntaxe moderne pour extraire le Base64 propre pour Dompdf
            $result = $writer->write($qrCode);
            $qrBase64 = $result->getDataUri();
        } catch (\Exception $e) {
            $qrBase64 = null;
        }


        // Configuration du moteur Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('admin/agency/ticket/ticket_pdf.html.twig', [
            'agency_name' => 'TransKin Express',
            'ticket' => $ticket,
            'qr_base64' => $qrBase64
        ]);

        $dompdf->loadHtml($html);

        // Figeage de la carrosserie thermique 80mm x 260mm (en points PostScript)
        $dompdf->setPaper([0, 0, 250.00, 737.00], 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Ticket.pdf"'
        ]);
    } //printTicketPdf


    /**
     * Utilisateur connecté, obligatoirement rattaché à une agence.
     */
    private function agencyUser(): User
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        if (!$user instanceof User || null === $user->getAgency()) {
            throw $this->createAccessDeniedException('Aucune agence n\'est associée à ce compte.');
        }

        return $user;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
