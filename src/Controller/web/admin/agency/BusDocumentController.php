<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\BusDocumentData;
use App\Entity\Bus;
use App\Entity\BusDocument;
use App\Enum\Scope;
use App\Form\agency\BusDocumentType;
use App\Service\BusDocumentService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class BusDocumentController extends AbstractAdminController
{
    public function __construct(
        private readonly BusDocumentService $documentService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/bus-documents', name: 'admin_agency_bus_document_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.view');
        $agency = $this->actor()->getAgency();

        $filters = [
            'type'   => (string) $request->query->get('type', ''),
            'status' => (string) $request->query->get('status', ''),
        ];

        $data = $this->documentService->listAgencyDocuments($agency, $filters);

        return $this->render('admin/agency/bus_document/index.html.twig', [
            'page'             => 'document',
            'documents'        => $data['documents'],
            'stats'            => $data['stats'],
            'types_list'       => $data['typesList'],
            'current_doc_type' => $filters['type'],
            'current_status'   => $filters['status'],
        ]);
    }

    #[Route('/admin/agency/bus/document/add', name: 'admin_agency_bus_document_add', methods: ['GET', 'POST'])]
    #[Route('/admin/agency/bus/{bus_code}/document/add', name: 'admin_agency_bus_choosen_document_add', methods: ['GET', 'POST'])]
    #[Route('/admin/agency/bus/{bus_code}/document/{doc_code}/edit', name: 'admin_agency_bus_document_edit', methods: ['GET', 'POST'])]
    public function addAndEditDocument(?string $doc_code = null, ?string $bus_code = null, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();
        $agency = $actor->getAgency();

        $isEdit = null !== $doc_code;
        $document = null;
        $title = $isEdit ? 'Modifier' : 'Enregistrer';

        if ($isEdit) {
            $document = $this->documentService->findAgencyDocument($agency, $doc_code);
            if (!$document instanceof BusDocument) {
                throw $this->createNotFoundException('Document introuvable dans votre agence.');
            }
            $bus_code = $document->getBus()?->getCode();
            $dto = $this->documentService->createDtoFromDocument($document, false);
        } else {
            $dto = new BusDocumentData();
            $dto->busCode = $bus_code;
        }

        $form = $this->createForm(BusDocumentType::class, $dto, [
            'mode'  => $isEdit ? 'edit' : 'create',
            'fleet' => $isEdit ? [] : $this->documentService->agencyBuses($agency),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($isEdit && $document instanceof BusDocument) {
                    $this->documentService->updateDocument($document, $actor, $dto, false);
                    $this->addFlash('success', sprintf('Le document « %s » a été mis à jour avec succès.', $document->getType()));
                    $redirectBusCode = $document->getBus()?->getCode();
                } else {
                    $createdDoc = $this->documentService->createDocument($actor, $dto);
                    $this->addFlash('success', sprintf('Le document « %s » a été enregistré avec succès.', $createdDoc->getType()));
                    $redirectBusCode = $createdDoc->getBus()?->getCode();
                }

                if (null !== $redirectBusCode && '' !== $redirectBusCode) {
                    return $this->redirectToRoute('admin_agency_bus_show', ['code' => $redirectBusCode]);
                }

                return $this->redirectToRoute('admin_agency_bus_document_index');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/bus_document/form.html.twig', [
            'page'     => 'document',
            'form'     => $form,
            'bus_code' => $bus_code,
            'doc_code' => $doc_code,
            'isEdit'   => $isEdit,
            'document' => $document,
            'title'    => $title,
        ]);
    }

    #[Route('/admin/agency/bus/{bus_code}/document/{doc_code}/renew', name: 'admin_agency_bus_document_renew', methods: ['GET', 'POST'])]
    public function renewDocument(string $bus_code, string $doc_code, Request $request): Response
    {
        $this->denyAccessUnlessGranted('bus.write');
        $actor = $this->actor();
        $agency = $actor->getAgency();

        $bus = $this->documentService->findAgencyBus($agency, $bus_code);
        if (!$bus instanceof Bus) {
            throw $this->createNotFoundException('Véhicule introuvable.');
        }

        $document = $this->documentService->findAgencyDocument($agency, $doc_code);
        if (!$document instanceof BusDocument || $document->getBus()?->getId() !== $bus->getId()) {
            throw $this->createNotFoundException('Document introuvable pour ce véhicule.');
        }

        $dto = $this->documentService->createDtoFromDocument($document, true);
        $form = $this->createForm(BusDocumentType::class, $dto, [
            'mode' => 'renew',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->documentService->updateDocument($document, $actor, $dto, true);
                $this->addFlash('success', sprintf('Le document « %s » a été renouvelé avec succès.', $document->getType()));

                return $this->redirectToRoute('admin_agency_bus_show', ['code' => $bus->getCode()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/bus_document/renew.html.twig', [
            'page'     => 'document',
            'form'     => $form,
            'document' => $document,
            'bus'      => $bus,
        ]);
    }

    #[Route('/admin/agency/bus-documents/download/{code}', name: 'admin_agency_bus_document_download_file', methods: ['GET'])]
    public function downloadFile(string $code): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('bus.view');
        $document = $this->requireAgencyDocument($code);

        try {
            $filePath = $this->documentService->resolveAbsoluteFilePath($document);
        } catch (\DomainException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $downloadName = sprintf('%s_%s.%s', $document->getBus()?->getPlateNumber() ?? 'bus', $document->getCode(), $extension);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }

    #[Route('/admin/agency/bus-documents/open/{code}', name: 'admin_agency_bus_document_open_file', methods: ['GET'])]
    public function openFile(string $code): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('bus.view');
        $document = $this->requireAgencyDocument($code);

        try {
            $filePath = $this->documentService->resolveAbsoluteFilePath($document);
        } catch (\DomainException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($filePath));

        return $response;
    }

    private function requireAgencyDocument(string $code): BusDocument
    {
        $document = $this->documentService->findAgencyDocument($this->actor()->getAgency(), $code);
        if (!$document instanceof BusDocument) {
            throw $this->createNotFoundException('Document introuvable dans votre agence.');
        }

        return $document;
    }
}
