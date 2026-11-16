<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Dto\admin\agency\TripPlanningData;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\TripStatus;
use App\Exception\TripPlanningException;
use App\Form\TripPlanningType;
use App\Service\AgencyTripListing;
use App\Service\BusLayoutGridBuilder;
use App\Service\TripOperationService;
use App\Service\TripPlanningService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TripController extends AbstractController
{
    public function __construct(
        private readonly TripOperationService $tripOperationService,
    ) {}

    /**
     * LISTE DES VOYAGES : statistiques et filtres (date exacte, ligne, état opérationnel).
     */
    #[Route('/admin/agency/trips', name: 'admin_agency_trip_index', methods: ['GET'])]
    public function index(Request $request, AgencyTripListing $listing): Response
    {
        $this->denyAccessUnlessGranted('trip.view');
        $user = $this->agencyUser();
        $agency = $user->getAgency();
        $branch = AgencyTripListing::scopeBranch($user);
        $now = new \DateTimeImmutable();

        $filters = [
            'date'   => $this->parseDate($request->query->get('filter_date')),
            'route'  => ctype_digit((string) $request->query->get('filter_route')) ? (int) $request->query->get('filter_route') : null,
            'status' => \array_key_exists((string) $request->query->get('filter_status'), AgencyTripListing::STATUS_FILTERS)
                ? (string) $request->query->get('filter_status')
                : null,
        ];

        return $this->render('admin/agency/trip/index.html.twig', [
            'page'          => 'trip',
            'stats'         => $listing->stats($agency, $branch, $now),
            'trips'         => $listing->search($agency, $branch, $filters, $now),
            'route_options' => $listing->routeOptions($agency),
            'filters'       => [
                'date'   => $filters['date']?->format('Y-m-d') ?? '',
                'route'  => $filters['route'],
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    /**
     * PLANIFICATION D'UN VOYAGE : crée le voyage et, en cascade, tous les sièges du bus.
     */
    #[Route('/admin/agency/trip/add', name: 'admin_agency_trip_add', methods: ['GET', 'POST'])]
    public function add(Request $request, TripPlanningService $planner): Response
    {
        $this->denyAccessUnlessGranted('trip.write');
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

                return $this->redirectToRoute('admin_agency_trip_show', ['code' => $trip->getCode()]);
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
    }

    /**
     * FICHE OPÉRATIONNELLE DU VOYAGE : détails, workflow et manifeste nominatif réel.
     */
    #[Route('/admin/agency/trip/show/{code}', name: 'admin_agency_trip_show', methods: ['GET'])]
    public function show(string $code): Response
    {
        $this->denyAccessUnlessGranted('trip.view');
        $trip = $this->requireAgencyTrip($code);

        return $this->render('admin/agency/trip/show.html.twig', [
            'page' => 'trip',
        ] + $this->tripOperationService->getTripDetails($trip));
    }

    /**
     * TRANSITION DU STATUT OPÉRATIONNEL DU VOYAGE (Embarquement, Départ, Arrivée, Annulation).
     */
    #[Route('/admin/agency/trip/{code}/status', name: 'admin_agency_trip_status', methods: ['POST'])]
    public function updateStatus(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('trip.write');
        $user = $this->agencyUser();
        $trip = $this->requireAgencyTrip($code);

        if (!$this->isCsrfTokenValid('trip_status_' . $trip->getCode(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $targetValue = (string) $request->request->get('target_status', '');
        $targetStatus = TripStatus::tryFrom($targetValue);

        if (!$targetStatus instanceof TripStatus) {
            $this->addFlash('error', 'Statut de voyage invalide.');
            return $this->redirectToRoute('admin_agency_trip_show', ['code' => $trip->getCode()]);
        }

        try {
            $this->tripOperationService->transitionStatus($trip, $targetStatus, $user);
            $this->addFlash('success', 'Le statut du voyage a été mis à jour.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_trip_show', ['code' => $trip->getCode()]);
    }

    #[Route('/admin/agency/trip/search/', name: 'admin_agency_trip_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        return $this->redirectToRoute('admin_agency_trip_index', $request->query->all());
    }

    /**
     * MANIFESTE PDF : COMPILATION ET EXPORT DE LA FEUILLE DE ROUTE RÉELLE VIA DOMPDF.
     */
    #[Route('/admin/agency/trips/{code}/manifest-pdf', name: 'admin_agency_trip_manifest_pdf', methods: ['GET'])]
    public function generateManifestPdf(string $code): Response
    {
        $this->denyAccessUnlessGranted('trip.view');
        $user = $this->agencyUser();
        $tripEntity = $this->requireAgencyTrip($code);
        $details = $this->tripOperationService->getTripDetails($tripEntity);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('admin/agency/trip/manifest_pdf.html.twig', [
            'agency_name'     => (string) $user->getAgency()?->getName(),
            'trip'            => $details['trip'],
            'passengers_list' => $details['passengers_list'],
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $filename = sprintf('Manifeste_%s.pdf', $details['trip']['tripNumber']);

        return new Response($pdfOutput, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/admin/agency/trips/{code}/direct-purchase', name: 'admin_agency_trip_direct_purchase', methods: ['GET'])]
    public function directPurchase(string $code, Request $request, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {
        $this->denyAccessUnlessGranted('ticket.write');
        $tripEntity = $this->requireAgencyTrip($code);

        $busLayout = [
            'id'               => 1,
            'name'             => 'Yutong 50 places modele 3-2',
            'agency'           => null,
            'rows'             => 11,
            'columns'          => 4,
            'aisles'           => [0 => 2],
            'specialPositions' => [
                ['type' => 'driver', 'row' => 1, 'col' => 1],
                ['type' => 'aisle', 'row' => 1, 'col' => 2],
                ['type' => 'door', 'row' => 2, 'col' => 4],
                ['type' => 'aisle', 'row' => 2, 'col' => 3],
            ],
            'hasBackExtraSeat' => true,
            'isValided'        => false,
            'createdAt'        => '2026-10-07 16:52:1',
            'resolvedAt'       => null,
        ];

        $trip = [
            'id'         => $tripEntity->getId(),
            'code'       => $tripEntity->getCode(),
            'tripNumber' => strtoupper((string) $tripEntity->getCode()),
            'route'      => (string) $tripEntity->getRoute()?->getName(),
            'capacity'   => $tripEntity->getTripSeats()->count(),
        ];

        $selectedSeat = $request->query->get('seat', '01');

        return $this->render('admin/agency/trip/direct_purchase.html.twig', [
            'page'          => 'trip',
            'trip'          => $trip,
            'selected_seat' => $selectedSeat,
            'fare_category' => 'VIP',
            'price'         => 45.00,
            'seatmap'       => $busLayoutGridBuilder->build($busLayout),
            'currency_code' => 'USD',
        ]);
    }

    #[Route('/admin/agency/trips/{code}/direct-purchase/process', name: 'admin_agency_trip_direct_purchase_process', methods: ['POST'])]
    public function processDirectPurchase(string $code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ticket.write');
        $newTicketId = 84;
        return $this->redirectToRoute('admin_agency_trip_ticket_print', ['id' => $newTicketId]);
    }
    #[Route('/admin/agency/trips/ticket/{id}/print', name: 'admin_agency_trip_ticket_print', methods: ['GET'])]
    public function printTicketPdf(int $id): Response
    {
        $this->denyAccessUnlessGranted('ticket.print');
        $ticket = [
            'id'              => $id,
            'number'          => 'TKT-202610-0084',
            'passenger_name'  => 'Jean Mukendi',
            'passenger_phone' => '+243 812 345 678',
            'route'           => 'Kinshasa ➔ Kikwit via Kenge',
            'departure_stop'  => 'Kinshasa (Gare Centrale)',
            'arrival_stop'    => 'Kikwit (Terminus)',
            'departure_date'  => new \DateTime('now'),
            'departure_time'  => '14h30',
            'bus_plate'       => '2432AB01 (Yutong)',
            'seat_number'     => '05',
            'fare_category'   => 'Standard',
            'amount'          => 45.00,
            'currency_code'   => 'USD',
            'security_hash'   => 'MDT-TKT-202610-0888-SECURE',
        ];
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
        } catch (\Exception) {
            $qrBase64 = null;
        }
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($pdfOptions);
        $html = $this->renderView('admin/agency/ticket/ticket_pdf.html.twig', [
            'agency_name' => (string) $this->agencyUser()->getAgency()?->getName(),
            'ticket'      => $ticket,
            'qr_base64'   => $qrBase64,
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 250.00, 737.00], 'portrait');
        $dompdf->render();
        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Ticket.pdf"',
        ]);
    }
    private function requireAgencyTrip(string $code): Trip
    {
        $trip = $this->tripOperationService->findAgencyTripByCode($this->agencyUser()->getAgency(), $code);
        if (!$trip instanceof Trip) {
            throw $this->createNotFoundException('Voyage introuvable dans votre agence.');
        }
        return $trip;
    }
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
