<?php
// src/Controller/web/admin/agency/BusLayoutController.php

namespace App\Controller\web\admin\platform;

use App\Service\BusLayoutGridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BusLayoutController extends AbstractController
{

    #[Route('/admin/platform/bus-layouts', name: 'admin_platform_bus_layout_index')]
    public function index(): Response
    {

        // Simulation du catalogue des plans de sièges d'usine mondiaux
        $layoutsList = [
            [
                'id' => 1,
                'code' => 'lyt-001',
                'name' => 'Modèle Standard Coaster (29 places)',
                'rows' => 10,
                'columns' => 4,
                'aisles' => [2],
                'isValided' => true,
                'hasBackExtraSeat' => true
            ],
            [
                'id' => 2,
                'code' => 'lyt-001',
                'name' => 'Modèle Grand Scania (55 places)',
                'rows' => 12,
                'columns' => 4,
                'aisles' => [3],
                'isValided' => true,
                'hasBackExtraSeat' => false
            ],
            [
                'id' => 3,
                'code' => 'lyt-001',
                'name' => 'Modèle VIP Salon (18 places larges)',
                'rows' => 8,
                'columns' => 3,
                'aisles' => [2],
                'isValided' => true,
                'hasBackExtraSeat' => true
            ],
            [
                'id' => 4,
                'code' => 'lyt-001',
                'name' => 'Modèle Urbain Sprinter (15 places)',
                'rows' => 5,
                'columns' => 3,
                'aisles' => [2],
                'isValided' => false,
                'hasBackExtraSeat' => false
            ]
        ];

        return $this->render('admin/platform/bus/index.html.twig', [
            'page' => 'bus_layout',
            'layouts_list' => $layoutsList
        ]);
    } //index


    #[Route('/admin/platform/bus-layout/add', name: 'admin_platform_bus_layout_add')]
    public function add(Request $request): Response
    {
        $session = $request->getSession();

        //Soumission du formulaire
        if ($request->isMethod('POST')) {

            // Récupérer TOUTES les données sous forme de tableau
            $allData = $request->request->all();

            // Construction des Special Position
            $specialPositions = [];

            if ($allData) {


                $types = $allData['specialPositionType'] ?? [];
                $rows = $allData['specialPositionRow'] ?? [];
                $cols = $allData['specialPositionCol'] ?? [];

                foreach ($types as $index => $type) {
                    $specialPositions[] = [
                        'type' => $type,
                        'row'  => (int) ($rows[$index] ?? 0),
                        'col'  => (int) ($cols[$index] ?? 0),
                    ];
                }
            }

            //Insertion dans la session => A SUPPRIMER UNE FOIS LA BD Mise en place
            $layouts = $session->get('bus_layout', []);
            $newId = empty($layouts)  ? 1 : max(array_keys($layouts)) + 1;
            //==============================================================//

            //Construction de Bus Layout
            $busLayout = [
                'id' => $newId,
                'name' => $allData['name'],
                'agency' => null,
                'rows' => (int) $allData['rows'],
                'columns' => (int) $allData['columns'],
                'aisles' => array_map('intval', $allData['aisles'] ?? []),
                'specialPositions' => $specialPositions,
                'hasBackExtraSeat' => isset($allData['backExtraSeat']),
                'isValided' => false,
                'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'resolvedAt' => null,
            ];


            //Insertion dans la session => A SUPPRIMER UNE FOIS LA BD Mise en place
            // ajout du nouveau layout
            $layouts[$newId] = $busLayout;

            // sauvegarde en session
            $session->set('bus_layout', $layouts);
            //==============================================================//
        }

        //Rendu du formulaire de creation de buslayout
        return $this->render('admin/platform/bus/bus_layout_creation.html.twig', [
            'page' => 'bus_layout',

        ]);
    } //add


    /**
     * MODIFICATION DU LIBELLÉ UNIQUEMENT (SÉCURITÉ FINANCIÈRE)
     */
    #[Route('/admin/platform/bus-layouts/{code}/modification', name: 'admin_platform_bus_layout_edit')]
    public function edit(string $code, Request $request): Response
    {
        // Simulation d'un gabarit incluant tes structures exactes de SpecialPositions
        $layout = [
            'id' => 5,
            'name' => 'Modèle Standard Coaster (29 places)',
            'rows' => 10,
            'columns' => 4,
            'specialPositions' => [
                ['type' => 'driver', 'row' => 1, 'col' => 1]
            ],
            'createdAt' => '2026-09-24 12:00:00'
        ];

        if ($request->isMethod('POST')) {
            $newName = $request->request->get('name');
            $this->addFlash('success', 'Le libellé commercial du modèle a été corrigé.');
            return $this->redirectToRoute('admin_platform_bus_layout_index');
        }

        return $this->render('admin/platform/bus/edit.html.twig', [
            'page' => 'bus_layout',
            'layout' => $layout
        ]);
    }//edit

    /**
     * COMPILATION ET DESSIN DU PLAN APPLICATIF DE TA TABLE 15. BusLayout
     */
    #[Route('/admin/platform/bus-layouts/{code}/details', name: 'admin_platform_bus_layout_show', methods: ['GET'])]
    public function show(string $code, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {
        // 1. Simulation d'un modèle d'usine brut selon tes critères de persistance exacts
        $layout = [
            'id' => 7,
            'code' => 777,
            'name' => 'Modèle Grand Scania (55 places)',
            'rows' => 12,
            'columns' => 4,
            'aisles' => [2], // Allée après la colonne 2
            'specialPositions' => [
                ['type' => 'driver', 'row' => 1, 'col' => 1],
                ['type' => 'aisle', 'row' => 1, 'col' => 2],
                ['type' => 'aisle', 'row' => 2, 'col' => 3],
                ['type' => 'door', 'row' => 2, 'col' => 4],
                ['type' => 'wc', 'row' => 12, 'col' => 3],
                ['type' => 'wc', 'row' => 12, 'col' => 4]
            ],
            'hasBackExtraSeat' => false,
            'isValided' => false,
            'createdAt' => '2026-09-23 15:30:22'
        ];


        return $this->render('admin/platform/bus/show.html.twig', [
            'page' => 'bus_layout',
            'layout' => $layout,
            'seatmap' => $busLayoutGridBuilder->build($layout)
        ]);
    } //show



    #[Route('/admin/platform/bus-layouts/{code}/toggle/active', name: 'admin_platform_bus_layout_toggle')]
    public function toggle_layout(string $code): Response
    {
        return $this->redirectToRoute('admin_platform_bus_layout_show', ['code' => $code]);
    }
}
