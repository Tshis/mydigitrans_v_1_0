<?php

namespace App\Controller\web\admin\agency;

use App\Service\BusLayoutGridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class BusController extends AbstractController
{
    #[Route('/admin/agency/bus/list', name: 'admin_agency_bus_index')]
    public function index(): Response
    {

        $fleet = [
            [
                'brand' => 'Mercedes-Benz',
                'model' => 'Sprinter 316',
                'type' => 'Minibus',
                'plateNumber' => 'A-1234-BC',
                'capacity' => 19,
                'mileage' => 142050,
                'docStatus' => 'up-to-date',
                'status' => 'available',
                'statusLabel' => 'Disponible'
            ],
            [
                'brand' => 'Toyota',
                'model' => 'Coaster',
                'type' => 'Bus Interurbain',
                'plateNumber' => 'A-5678-DE',
                'capacity' => 30,
                'mileage' => 89400,
                'docStatus' => 'up-to-date',
                'status' => 'on_road',
                'statusLabel' => 'En Voyage'
            ],
            [
                'brand' => 'Scania',
                'model' => 'K410',
                'type' => 'Autocar Grand Confort',
                'plateNumber' => 'A-9012-FG',
                'capacity' => 54,
                'mileage' => 310200,
                'docStatus' => 'expired',
                'status' => 'broken',
                'statusLabel' => 'En Panne'
            ]
        ];




        return $this->render('admin/agency/bus/index.html.twig', [
            'page' => 'bus',
            'fleet' => $fleet
        ]);
    } //index

    #[Route('/admin/agency/bus/add', name: 'admin_agency_bus_add')]
    public function add(): Response
    {
        return $this->render('admin/agency/bus/add.html.twig', [
            'page' => 'bus',
        ]);
    } //add


    #[Route('/admin/agency/bus/{code}/details', name: 'admin_agency_bus_show')]
    public function show(string $code, Request $request, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {
        $session = $request->getSession();

        $layouts = $session->get('bus_layout', []);

        // simulation bus -> layout
        $busToLayoutMap = [
            'bus-001' => 1,
            'bus-002' => 2,
            'bus-003' => 3,
        ];

        $layoutId = $busToLayoutMap[$code] ?? null;

        $busLayout = $layouts[$layoutId] ?? null;

        if (!$busLayout) {
            throw $this->createNotFoundException('Bus layout introuvable');
        }

        $bus = [
            'id' => 1002,
            'brand' => 'Toyota',
            'model' => 'Coaster',
            'plateNumber' => 'A-5678-DE',
            'capacity' => 30,
            'mileage' => 89400,
            'vin' => 'JT153JA0004912',
            'status' => 'maintenance',
            'statusLabel' => 'Au Garage',
            'currency' => 'CDF',
            'code' => 'bus-001',

            // Simulation de la table historique des incidents techniques
            'maintenance_history' => [
                [
                    'id' => 4021,
                    'reportedAt' => new \DateTime('2026-08-10'),
                    'issue' => 'Surchauffe moteur sur la route de Matadi',
                    'solution' => 'Remplacement du joint de culasse et purge du radiateur',
                    'reportedBy' => 'Maitre Kabeya',
                    'status' => 'resolved',
                    'resolvedAt' => new \DateTime('2026-08-15'),
                    'updatedBy' => 'Kalonji Beya',
                ],
                [
                    'id' => 4156,
                    'reportedAt' => new \DateTime('2026-08-25'),
                    'issue' => 'Amortisseurs arrières usés (RN1)',
                    'solution' => '',
                    'status' => 'in_progress',
                    'reportedBy' => 'Maitre Kabeya',
                    'resolvedAt' => null,
                    'updatedBy' => null,
                ]
            ],


            // --- NOUVEAU : PORTFOLIO ADMINISTRATIF DU BUS ---
            'documents' => [
                [
                    'type' => 'Assurance Obligatoire SONAS',
                    'referenceNumber' => 'AS-SON-2026-884',
                    'issuedAt' => new \DateTime('2025-09-01'),
                    'expiredAt' => new \DateTime('2026-09-01'), // Expire bientôt par rapport au 29 août 2026
                    'status' => 'warning',
                ],
                [
                    'type' => 'Contrôle Technique (Feuille Jaune)',
                    'referenceNumber' => 'CT-CTCE-9942',
                    'issuedAt' => new \DateTime('2026-03-15'),
                    'expiredAt' => new \DateTime('2026-09-15'),
                    'status' => 'valid',
                ],
                [
                    'type' => 'Autorisation de Transport Interurbain',
                    'referenceNumber' => 'AT-MIN-00482',
                    'issuedAt' => new \DateTime('2025-01-10'),
                    'expiredAt' => new \DateTime('2026-01-10'), // Déjà expiré !
                    'status' => 'expired',
                ]
            ],



        ];

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


        return $this->render('admin/agency/bus/show.html.twig', [
            'page' => 'bus',
            'bus_code' => $code,
            'bus' => $bus,
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
            'documents' => $documents
        ]);
    } ////show()


    //=======Gestion Documentaires========
    #[Route('/admin/agency/bus/{bus_code}/document/add', name: 'admin_agency_bus_document_add')]
    #[Route('/admin/agency/bus/{bus_code}/document/{doc_code}/edit', name: 'admin_agency_bus_document_edit')]
    function add_and_edit_document(?string $doc_code = null, string $bus_code, Request $request): Response
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

        return $this->render('admin/agency/bus/document_add_and_edit.html.twig', [
            'page' => 'bus',
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

        return $this->render('admin/agency/bus/document_renew.html.twig', [
            'page' => 'fleet_document_renew',
            'document' => $document,
            'bus' => $bus // Branché textuellement sur ta boucle {% for bus in fleet %}
        ]);
    } //renewDocument



    #[Route('/telecharger-fichier/{code}', name: 'admin_agency_bus_download_file')]
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
}
