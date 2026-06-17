<?php
// src/Controller/web/admin/agency/BusLayoutController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BusLayoutController extends AbstractController
{
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
            'page' => 'bus',

        ]);
    } //add

    #[Route('/admin/platform/bus-layout/save-ajax', name: 'admin_platform_bus_layout_save_ajax', methods: ['POST'])]
    public function saveAjax(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $allData = json_decode($request->getContent(), true);

        if (!$allData || !isset($allData['name'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }

        // Récupération de l'historique en session
        $layouts = $session->get('mock_layouts', []);

        // Génération d'un ID auto-incrémenté artificiel
        $newId = count($layouts) > 0 ? max(array_keys($layouts)) + 1 : 1;

        // Stockage du gabarit complet conforme aux colonnes de ton MCD
        $layouts[$newId] = [
            'id' => $newId,
            'name' => $allData['name'],
            'rows' => (int)$allData['rows'],
            'columns' => (int)$allData['columns'],
            'aisles' => $allData['aisles'], // Tableau JSON ex: ["2"]
            'hasBackExtraSeat' => (bool)$allData['hasBackExtraSeat'],
            'matrix' => $allData['matrix'] // Les coordonnées individuelles dessinées
        ];

        // Écriture en session
        $session->set('mock_layouts', $layouts);

        return new JsonResponse([
            'success' => true,
            'id' => $newId,
            'name' => $allData['name']
        ]);
    } //saveAjax
}
