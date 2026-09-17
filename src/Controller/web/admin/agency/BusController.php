<?php

namespace App\Controller\web\admin\agency;

use App\Service\BusLayoutGridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;



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




    #[Route('/admin/agency/bus/add', name: 'admin_agency_bus_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            dd($request);
            // Réception immédiate et propre de tes inputs HTML générés par la carrosserie
            $types = $request->request->all('specialPositionType');
            $rows = $request->request->all('specialPositionRow');
            $cols = $request->request->all('specialPositionCol');

            $aisles = $request->request->all('aisles'); // Allées du véhicule
            $plateNumber = $request->request->get('plate_number');

            $busSeatsPayload = [];

            if (!empty($types)) {
                foreach ($types as $index => $type) {
                    $busSeatsPayload[] = [
                        'type' => $type,
                        'row'  => (int)($rows[$index] ?? 0),
                        'col'  => (int)($cols[$index] ?? 0)
                    ];
                }
            }

            // $busSeatsPayload contient maintenant toutes tes cases cliquables ! 
            // Ton code persist_data Doctrine s'applique de façon 100% robuste.

            $this->addFlash('success', sprintf('Le bus immatriculé %s a été configuré et inséré.', $plateNumber));
            return $this->redirectToRoute('admin_agency_bus_index');
        }

        return $this->render('admin/agency/bus/add.html.twig', [
            'page' => 'bus',
            'branches_list' => [['code' => 'SUC-KIN-01', 'name' => 'Victoire']]
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
            'bus-004' => 4,
            'bus-005' => 5,
            'bus-006' => 6,
            'bus-007' => 7,
            'bus-008' => 8,
            'bus-009' => 9,
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



}
