<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class BusDocumentController extends AbstractController
{

    #[Route('/admin/agency/bus-documents', name: 'admin_agency_bus_document_index')]
    public function index(): Response
    {


        // Simulation des pièces administratives selon ton entité 10. BusDocuments
        $documents = [
            [

                'type' => 'Assurance',
                'referenceNumber' => 'POL-SON-99824',
                'issuedAt' => new \DateTime('2025-09-10'),
                'expiredAt' => new \DateTime('2026-09-10'),
                'status' => 'valid',
                'url' => null,
                'code' => 123,
                'updatedBy' => 'Kalonji Beya'
            ],
            [
                'type' => 'contrôle technique',
                'referenceNumber' => 'CT-CE-4482',
                'issuedAt' => new \DateTime('2026-04-01'),
                'expiredAt' => new \DateTime('2026-10-01'),
                'status' => 'valid',
                'url' => '/uploads/documents/auth-003.pdf',
                'code' => 1234,
                'updatedBy' => 'Daniel Lukonu'
            ],
            [
                'type' => 'Autorisation de transport',
                'referenceNumber' => null, // Nullable dans ton MCD
                'issuedAt' => new \DateTime('2025-01-15'),
                'expiredAt' => new \DateTime('2026-01-15'), // Déjà expiré !
                'status' => 'expired',
                'url' => '/uploads/documents/auth-003.pdf',
                'code' => 12345,
                'updatedBy' => 'Kalonji Beya'
            ]
        ];

        return $this->render('admin/agency/bus_document/index.html.twig', [
            'page' => 'document',
            'documents' => $documents,
            'current_doc_type' => '',
            'current_status' => '',
            'date_from' => '',
            'date_to' => '',
            'current_branch' => '',
            'branches_list' => []
        ]);
    } //index

    //=======Gestion Documentaires========
    #[Route('/admin/agency/bus/document/add', name: 'admin_agency_bus_document_add')]
    #[Route('/admin/agency/bus/{bus_code}/document/add', name: 'admin_agency_bus_choosen_document_add')]
    #[Route('/admin/agency/bus/{bus_code}/document/{doc_code}/edit', name: 'admin_agency_bus_document_edit')]
    function add_and_edit_document(?string $doc_code = null, ?string $bus_code = null, Request $request): Response
    {

        // Réutilisation rigoureuse de ton tableau de flotte de bus simulé
        $fleet = [
            ['code' => 'bus-001', 'brand' => 'Toyota', 'model' => 'Coaster', 'plateNumber' => 'A-5678-DE'],
            ['code' => 'bus-002', 'brand' => 'Mercedes-Benz', 'model' => 'Sprinter', 'plateNumber' => 'A-1234-BC'],
            ['code' => 'bus-003', 'brand' => 'Scania', 'model' => 'K410', 'plateNumber' => 'A-9012-FG'],
        ];

        // Détection intelligente du mode : si doc_code est présent, on est en édition 
        $isEdit = $doc_code !== null;
        $document = null;
        $title = "Enregistrer";

        if ($isEdit) {
            $title = "Modifier";
            // Simulation des données de la pièce légale récupérée pour pré-remplir le formulaire
            $document = [
                'code' => $doc_code,
                'type' => 'Assurance',
                'referenceNumber' => 'POL-SON-99824',
                'issuedAt' => new \DateTime('2025-09-10'),
                'expiredAt' => new \DateTime('2026-09-10'),
                'status' => 'valid',
                'url' => '/uploads/documents/assurance.pdf',
                'updatedBy' => 'Daniel Lukonu'
            ];
        }

        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');
            $status = $request->request->get('status');

            $this->addFlash('success', sprintf(
                'Document de type %s enregistré avec succès au statut %s.',
                strtoupper($type),
                strtoupper($status)
            ));

            return $this->redirectToRoute('admin_agency_bus_show', ['code' => $bus_code]);
        }

        return $this->render('admin/agency/bus_document/form.html.twig', [
            'page' => 'document',
            'fleet' => $fleet, // Branché textuellement sur ta boucle {% for bus in fleet %}
            'bus_code' => $bus_code,
            'doc_code' => $doc_code,
            'isEdit' => $isEdit,
            'document' => $document,
            'title' => $title
        ]);
    } //add_and_edit_document


    #[Route('/admin/agency/bus/{bus_code}/document/{doc_code}/renew', name: 'admin_agency_bus_document_renew')]
    function renewDocument(string $bus_code, string $doc_code, Request $request): Response
    {

        // Réutilisation rigoureuse de ton tableau de flotte de bus simulé
        $bus = [
            'code' => 'bus-001',
            'brand' => 'Toyota',
            'model' => 'Coaster',
            'plateNumber' => 'A-5678-DE'
        ];


        $document = [
            'code' => $doc_code,
            'type' => 'Controle technique',
            'referenceNumber' => 'POL-SON-99824',
            'issuedAt' => new \DateTime('2025-09-10'),
            'expiredAt' => new \DateTime('2026-09-10'),
            'status' => 'valid',
            'url' => '/uploads/documents/assurance.pdf',
            'updatedBy' => 'Daniel Lukonu'
        ];

        return $this->render('admin/agency/bus_document/renew.html.twig', [
            'page' => 'document',
            'document' => $document,
            'bus' => $bus // Branché textuellement sur ta boucle {% for bus in fleet %}
        ]);
    } //renewDocument

    #[Route('/telecharger-fichier/{code}', name: 'admin_agency_bus_document_download_file')]
    public function downloadFile($code): BinaryFileResponse
    {

        //traitement ici pour recuperer le doc et par consequence le fichier

        // 1. Définir le chemin absolu vers le fichier
        // Ici, le fichier est stocké dans le dossier "var/files/" à la racine du projet
        $filePath = $this->getParameter('kernel.project_dir') . '/var/files/rapport.pdf';

        // 2. Vérifier si le fichier existe réellement
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }

        // 3. Initialiser la réponse avec le fichier
        $response = new BinaryFileResponse($filePath);

        // 4. Forcer le téléchargement dans le navigateur (Mode ATTACHMENT)
        // Vous pouvez aussi renommer le fichier final téléchargé par l'utilisateur
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'mon_rapport_final.pdf'
        );

        return $response;
    } //downloadFile



    #[Route('/ouvrir-fichier/{code}', name: 'admin_agency_bus_document_open_file')]
    public function openFile($code): BinaryFileResponse
    {

        // 1. Définir le chemin absolu vers le fichier
        $filePath = $this->getParameter('kernel.project_dir') . '/var/files/rapport.pdf';

        // 2. Vérifier si le fichier existe réellement
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }

        // 3. Initialiser la réponse avec le fichier
        $response = new BinaryFileResponse($filePath);

        // 4. Permettre l'affichage dans le navigateur (Mode INLINE)
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            'mon_rapport_final.pdf'
        );

        return $response;
    } //openFile


}
