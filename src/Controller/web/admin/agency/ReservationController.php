<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\public\line\BookingData;
use App\Entity\Reservation;
use App\Entity\Trip;
use App\Enum\Scope;
use App\Exception\BookingException;
use App\Form\BookingType;
use App\Service\AgencyReservationService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractAdminController
{
    public function __construct(
        private readonly AgencyReservationService $reservationService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/reservations', name: 'admin_agency_reservation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('reservation.view');
        $agency = $this->actor()->getAgency();

        $filters = [
            'query'   => trim((string) $request->query->get('search_query', '')) ?: null,
            'status'  => trim((string) $request->query->get('ticket_status', '')) ?: null,
            'routeId' => ctype_digit((string) $request->query->get('route_filter'))
                ? (int) $request->query->get('route_filter')
                : null,
        ];

        return $this->render('admin/agency/reservation/index.html.twig', [
            'page'    => 'reservation',
            'filters' => $filters,
        ] + $this->reservationService->listAgencyReservations($this->actor(), $filters));
    }

    #[Route('/admin/agency/reservation/add/{code}', name: 'admin_agency_reservation_add', methods: ['GET', 'POST'])]
    public function add(Request $request, string $code): Response
    {
        $this->denyAccessUnlessGranted('reservation.write');
        $actor  = $this->actor();
        $agency = $actor->getAgency();

        $trip = $this->reservationService->findAgencyTripByCode($agency, $code);
        if (!$trip instanceof Trip) {
            throw $this->createNotFoundException('Voyage introuvable dans votre agence.');
        }

        $viewData = $this->reservationService->buildCreateViewData($trip);
        $stops = $viewData['stops'];

        $data = new BookingData();
        $data->seats = [null];
        if ([] !== $stops) {
            $data->departureStop = (string) reset($stops)->getId();
            $data->arrivalStop   = (string) end($stops)->getId();
        }

        $form = $this->createForm(BookingType::class, $data, [
            'csrf_protection' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isCsrfTokenValid('station_reservation_' . $trip->getCode(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité expiré. Veuillez réessayer.');
                return $this->redirectToRoute('admin_agency_reservation_add', ['code' => $trip->getCode()]);
            }

            try {
                $depId = (string) ($request->request->get('departure_stop_id') ?: $data->departureStop);
                $arrId = (string) ($request->request->get('arrival_stop_id') ?: $data->arrivalStop);
                $seatLabels = implode(',', array_filter(array_map('trim', $data->seats)));

                $reservation = $this->reservationService->createStationReservation(
                    trip: $trip,
                    departureStopId: $depId,
                    arrivalStopId: $arrId,
                    seatNumber: $seatLabels,
                    passengerName: (string) $data->fullName,
                    passengerPhone: (string) $data->phone,
                    actor: $actor
                );

                $this->addFlash('success', sprintf('Bon de réservation [%s] généré avec succès.', $reservation->getReference()));

                return $this->redirectToRoute('admin_agency_reservation_show', [
                    'reference' => $reservation->getReference(),
                ]);
            } catch (BookingException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/reservation/add.html.twig', [
            'page' => 'reservation',
            'form' => $form->createView(),
        ] + $viewData);
    }

    #[Route('/admin/agency/reservation/{reference}', name: 'admin_agency_reservation_show', methods: ['GET'])]
    public function show(string $reference): Response
    {
        $this->denyAccessUnlessGranted('reservation.view');
        $reservation = $this->requireAgencyReservation($reference);

        return $this->render('admin/agency/reservation/show.html.twig', [
            'page' => 'reservation',
        ] + $this->reservationService->getReservationDetails($reservation));
    }

    #[Route('/admin/agency/reservation/{reference}/cancel', name: 'admin_agency_reservation_cancel', methods: ['POST'])]
    public function cancel(string $reference, Request $request): Response
    {
        $this->denyAccessUnlessGranted('reservation.write');
        $actor       = $this->actor();
        $reservation = $this->requireAgencyReservation($reference);

        if (!$this->isCsrfTokenValid('cancel_reservation_' . $reservation->getReference(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $reason = (string) $request->request->get('cancellation_reason', '');
            $this->reservationService->cancelReservation($reservation, $reason, $actor);
            $this->addFlash('success', sprintf('La réservation [%s] a été annulée et les places ont été libérées.', $reservation->getReference()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_reservation_show', [
            'reference' => $reservation->getReference(),
        ]);
    }

    #[Route('/admin/agency/reservations/{reference}/print-voucher', name: 'admin_agency_reservation_print_voucher', methods: ['GET'])]
    public function printVoucherPdf(string $reference): Response
    {
        $this->denyAccessUnlessGranted('reservation.view');
        $actor       = $this->actor();
        $reservation = $this->requireAgencyReservation($reference);
        $details     = $this->reservationService->getReservationDetails($reservation)['reservation'];

        $voucher = [
            'id'              => $details['id'],
            'code'            => $details['reference'],
            'reference'       => $details['reference'],
            'trip_code'       => strtoupper($details['tripCode']),
            'passenger_name'  => $details['passengerName'],
            'passenger_phone' => $details['passengerPhone'],
            'departure_stop'  => $details['departureStop'],
            'arrival_stop'    => $details['arrivalStop'],
            'departure_date'  => $details['departureDate'],
            'departure_time'  => $details['departureTime'],
            'seat_number'     => $details['seatNumber'],
            'fare_category'   => $details['fareCategory'],
            'amount'          => $details['amount'],
            'currency_code'   => $details['currencyCode'],
        ];

        $qrCode = new QrCode(
            data: $voucher['code'],
            encoding: new Encoding('UTF-8'),
            size: 150,
            margin: 0
        );

        $writer = new PngWriter();
        $qrBase64 = null;

        try {
            $qrBase64 = $writer->write($qrCode)->getDataUri();
        } catch (\Exception) {
            $qrBase64 = null;
        }

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('admin/agency/reservation/voucher.html.twig', [
            'agency_name' => (string) $actor->getAgency()?->getName(),
            'voucher'     => $voucher,
            'qr_base64'   => $qrBase64,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 250.00, 737.00], 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="Bon_%s.pdf"', $voucher['code']),
        ]);
    }

    private function requireAgencyReservation(string $reference): Reservation
    {
        $reservation = $this->reservationService->findAgencyReservationByReference($this->actor()->getAgency(), $reference);
        if (!$reservation instanceof Reservation) {
            throw $this->createNotFoundException('Réservation introuvable dans votre agence.');
        }

        return $reservation;
    }
}
