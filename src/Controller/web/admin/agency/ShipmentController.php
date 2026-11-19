<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\ShipmentCreateData;
use App\Dto\admin\agency\ShipmentPickupData;
use App\Entity\Shipment;
use App\Enum\Scope;
use App\Form\agency\ShipmentCreateType;
use App\Form\agency\ShipmentPickupType;
use App\Service\AgencyShipmentService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ShipmentController extends AbstractAdminController
{
    public function __construct(
        private readonly AgencyShipmentService $shipmentService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/shipments', name: 'admin_agency_shipment_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('shipment.view');
        $agency = $this->actor()->getAgency();

        $filters = [
            'query'  => trim((string) $request->query->get('search_query', '')) ?: null,
            'status' => trim((string) $request->query->get('shipment_status', '')) ?: null,
        ];

        return $this->render('admin/agency/shipment/index.html.twig', [
            'page'    => 'shipment',
            'filters' => $filters,
        ] + $this->shipmentService->listAgencyShipments($agency, $filters));
    }

    #[Route('/admin/agency/shipment/add', name: 'admin_agency_shipment_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $this->denyAccessUnlessGranted('shipment.write');
        $actor  = $this->actor();
        $agency = $actor->getAgency();

        $data = new ShipmentCreateData();
        $data->departureAgencyBranch = $actor->getBranch();

        $form = $this->createForm(ShipmentCreateType::class, $data, [
            'agency' => $agency,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $shipment = $this->shipmentService->createShipment($data, $actor);
                $this->addFlash('success', sprintf(
                    'Colis [%s] enregistré. Code secret de retrait : %s',
                    $shipment->getReference(),
                    $shipment->getPickupCode()
                ));

                return $this->redirectToRoute('admin_agency_shipment_show', [
                    'reference' => $shipment->getReference(),
                ]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/shipment/add.html.twig', [
            'page' => 'shipment',
            'form' => $form->createView(),
        ] + $this->shipmentService->buildTripsPricingMetadata($agency), new Response(
            status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK
        ));
    }

    #[Route('/admin/agency/shipment/{reference}', name: 'admin_agency_shipment_show', methods: ['GET'])]
    public function show(string $reference): Response
    {
        $this->denyAccessUnlessGranted('shipment.view');
        $shipment = $this->requireAgencyShipment($reference);

        return $this->render('admin/agency/shipment/show.html.twig', [
            'page' => 'shipment',
        ] + $this->shipmentService->getShipmentDetails($shipment));
    }

    #[Route('/admin/agency/shipment/{reference}/cancel', name: 'admin_agency_shipment_cancel', methods: ['POST'])]
    public function cancel(string $reference, Request $request): Response
    {
        $this->denyAccessUnlessGranted('shipment.write');
        $actor    = $this->actor();
        $shipment = $this->requireAgencyShipment($reference);

        if (!$this->isCsrfTokenValid('cancel_shipment_' . $shipment->getReference(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $reason = trim((string) $request->request->get('cancel_reason', '')) ?: null;
            $this->shipmentService->cancelShipment($shipment, $reason, $actor);
            $this->addFlash('success', sprintf('L\'expédition [%s] a été annulée.', $shipment->getReference()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_shipment_show', [
            'reference' => $shipment->getReference(),
        ]);
    }


    #[Route('/admin/agency/shipment/print/{reference}', name: 'admin_agency_shipment_print', methods: ['GET'])]
    public function printFretVouchers(string $reference): Response
    {
        $this->denyAccessUnlessGranted('shipment.view');
        $actor    = $this->actor();
        $shipment = $this->requireAgencyShipment($reference);
        $details  = $this->shipmentService->getShipmentDetails($shipment)['shipment'];

        $now = new \DateTimeImmutable();
        $createdAt = $shipment->getCreatedAt() ?? $now;
        $minutesSinceCreation = (int) floor(($now->getTimestamp() - $createdAt->getTimestamp()) / 60);

        $printMode = ($minutesSinceCreation <= 15) ? 'creation' : 'duplicate';

        $package = [
            'id'             => $details['id'],
            'reference'      => $details['reference'],
            'waybill'        => $details['waybill'],
            'pickup_code'    => $details['pickupCode'],
            'sender_name'    => $details['senderName'],
            'sender_phone'   => $details['senderPhone'],
            'receiver_name'  => $details['receiverName'],
            'receiver_phone' => $details['receiverPhone'],
            'route'          => $details['route'],
            'arrival_branch' => $details['arrivalBranch'],
            'nature'         => sprintf('%s (%s)', $details['description'], $details['nature']),
            'weight'         => $details['weight'],
            'amount_paid'    => $details['price'],
            'currency'       => $details['currency'],
            'created_at'     => $createdAt,
        ];

        $qrBase64 = null;
        if ('creation' === $printMode) {
            $qrCode = new QrCode(
                data: 'MDT-FRET-' . $package['waybill'],
                encoding: new Encoding('UTF-8'),
                size: 150,
                margin: 0
            );
            $writer = new PngWriter();
            try {
                $qrBase64 = $writer->write($qrCode)->getDataUri();
            } catch (\Exception) {
                $qrBase64 = null;
            }
        }

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('admin/agency/shipment/fret_vouchers_pdf.html.twig', [
            'agency_name' => (string) $actor->getAgency()?->getName(),
            'package'     => $package,
            'print_mode'  => $printMode,
            'qr_base64'   => $qrBase64,
        ]);

        $dompdf->loadHtml($html);
        $paperHeight = ('creation' === $printMode) ? 793.70 : 425.20;
        $dompdf->setPaper([0, 0, 250.00, $paperHeight], 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="Fret_%s.pdf"', $package['waybill']),
        ]);
    }

    #[Route('/admin/agency/shipment/{reference}/pickup', name: 'admin_agency_shipment_pickup', methods: ['GET', 'POST'])]
    public function pickupForm(string $reference, Request $request): Response
    {
        $this->denyAccessUnlessGranted('shipment.write');
        $actor    = $this->actor();
        $shipment = $this->requireAgencyShipment($reference);
        $details  = $this->shipmentService->getShipmentDetails($shipment)['shipment'];

        $pickupData = new ShipmentPickupData();
        $form = $this->createForm(ShipmentPickupType::class, $pickupData);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$this->isCsrfTokenValid('pickup_shipment_' . $shipment->getReference(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité expiré. Veuillez réessayer.');
                return $this->redirectToRoute('admin_agency_shipment_pickup', ['reference' => $shipment->getReference()]);
            }

            if ($form->isValid()) {
                try {
                    $this->shipmentService->processPickup($shipment, $pickupData, $actor);
                    $this->addFlash('success', sprintf('Livraison du colis [%s] confirmée et décharge enregistrée.', $shipment->getReference()));

                    return $this->redirectToRoute('admin_agency_shipment_show', [
                        'reference' => $shipment->getReference(),
                    ]);
                } catch (\DomainException $e) {
                    $form->get('pickupCode')->addError(new FormError($e->getMessage()));
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }


        return $this->render('admin/agency/shipment/pickup.html.twig', [
            'page'    => 'shipment',
            'form'    => $form->createView(),
            'package' => [
                'id'             => $details['id'],
                'reference'      => $details['reference'],
                'waybill'        => $details['waybill'],
                'sender_name'    => $details['senderName'],
                'receiver_name'  => $details['receiverName'],
                'receiver_phone' => $details['receiverPhone'],
                'arrival_branch' => $details['arrivalBranch'],
                'nature'         => sprintf('%s (%s)', $details['description'], $details['nature']),
                'weight'         => $details['weight'],
            ],
        ]);
    }



    #[Route('/admin/agency/shipment/{reference}/ready', name: 'admin_agency_shipment_ready', methods: ['POST'])]
    public function markReady(string $reference, Request $request): Response
    {
        $this->denyAccessUnlessGranted('shipment.write');
        $actor    = $this->actor();
        $shipment = $this->requireAgencyShipment($reference);

        if (!$this->isCsrfTokenValid('ready_shipment_' . $shipment->getReference(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $password    = (string) $request->request->get('agent_password', '');
            $observation = trim((string) $request->request->get('reception_observation', '')) ?: null;

            $this->shipmentService->markReadyForPickup($shipment, $password, $observation, $actor);
            $this->addFlash('success', sprintf(
                'Colis [%s] réceptionné en magasin (%s) sous la responsabilité de %s.',
                $shipment->getReference(),
                $shipment->getArrivalAgencyBranch()?->getName(),
                $actor->getFullName()
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_shipment_show', [
            'reference' => $shipment->getReference(),
        ]);
    }





    private function requireAgencyShipment(string $reference): Shipment
    {
        $shipment = $this->shipmentService->findAgencyShipmentByReference($this->actor()->getAgency(), $reference);
        if (!$shipment instanceof Shipment) {
            throw $this->createNotFoundException('Expédition introuvable dans votre agence.');
        }

        return $shipment;
    }
}
