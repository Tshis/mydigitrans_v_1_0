<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Entity\Ticket;
use App\Enum\Scope;
use App\Service\AgencyTicketService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TicketController extends AbstractAdminController
{
    public function __construct(
        private readonly AgencyTicketService $ticketService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/tickets', name: 'admin_agency_ticket_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ticket.view');
        $actor = $this->actor();

        $filters = [
            'status' => trim((string) $request->query->get('status', '')) ?: null,
            'date'   => trim((string) $request->query->get('date', '')) ?: null,
            'query'  => trim((string) $request->query->get('query', '')) ?: null,
        ];

        return $this->render('admin/agency/ticket/index.html.twig', [
            'page'    => 'ticket',
            'filters' => $filters,
            'tickets' => $this->ticketService->listAgencyTickets($actor, $filters),
        ]);
    }

    #[Route('/admin/agency/tickets/{ticketNumber}/print', name: 'admin_agency_ticket_print', methods: ['GET'])]
    public function printPdf(string $ticketNumber): Response
    {
        $this->denyAccessUnlessGranted('ticket.view');
        $actor = $this->actor();
        $ticket = $this->ticketService->findAgencyTicketByNumber($actor->getAgency(), $ticketNumber);

        if (!$ticket instanceof Ticket) {
            throw $this->createNotFoundException('Billet de transport introuvable.');
        }

        $ticketPayload = $this->ticketService->prepareTicketPdfPayload($ticket, $actor);

        $qrCode = new QrCode(
            data: $ticketPayload['number'],
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
        $html = $this->renderView('admin/agency/ticket/ticket_pdf.html.twig', [
            'agency_name' => (string) $actor->getAgency()?->getName(),
            'ticket'      => $ticketPayload,
            'qr_base64'   => $qrBase64,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 250.00, 737.00], 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="Billet_%s.pdf"', $ticketPayload['number']),
        ]);
    }

    #[Route('/admin/agency/tickets/{ticketNumber}/cancel', name: 'admin_agency_ticket_cancel', methods: ['GET', 'POST'])]
    public function cancel(string $ticketNumber): Response
    {
        $this->denyAccessUnlessGranted('ticket.write');
        $actor = $this->actor();
        $ticket = $this->ticketService->findAgencyTicketByNumber($actor->getAgency(), $ticketNumber);

        if (!$ticket instanceof Ticket) {
            throw $this->createNotFoundException('Billet de transport introuvable.');
        }

        try {
            $this->ticketService->cancelTicket($ticket, $actor);
            $this->addFlash('success', sprintf('Le billet [%s] a été annulé et le siège libéré.', $ticket->getTicketNumber()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_ticket_index');
    }

    #[Route('/admin/agency/ticket/scan', name: 'admin_agency_ticket_scan', methods: ['GET'])]
    public function scan(): Response
    {
        $this->denyAccessUnlessGranted('ticket.view');

        return $this->render('admin/agency/ticket/scan.html.twig', [
            'page' => 'ticket',
            'recent_scans' => $this->ticketService->getRecentScanLogs($this->actor()->getAgency()),
        ]);
    }

    #[Route('/admin/agency/ticket/scan/verify', name: 'admin_agency_ticket_scan_verify', methods: ['POST'])]
    public function verifyScan(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ticket.view');
        $actor = $this->actor();
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $code = (string) ($payload['code'] ?? $request->request->get('code', ''));

        $result = $this->ticketService->verifyAndBoardTicket($actor->getAgency(), $code, $actor);

        return new JsonResponse($result, $result['valid'] ? 200 : 422);
    }
}
