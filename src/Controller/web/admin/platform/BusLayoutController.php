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
    #[Route('/admin/platform/bus-layout/add', name: 'admin_platform_bus_layout_add', methods: ['GET'])]
    public function add(Request $request): Response
    {
        $session = $request->getSession();

        // Initialisation de la session avec des gabarits standards si elle est vide
        if (!$session->has('mock_layouts')) {
            $session->set('mock_layouts', [
                1 => ['id' => 1, 'name' => 'Modèle Standard Coaster (2-2)', 'columns' => 4, 'rows' => 8],
                2 => ['id' => 2, 'name' => 'Modèle Grand Scania (2-2 + WC)', 'columns' => 5, 'rows' => 12],
                3 => ['id' => 3, 'name' => 'Modèle VIP (2-1)', 'columns' => 3, 'rows' => 8]
            ]);
        }

        return $this->render('admin/platform/bus/bus_layout_creation.html.twig', [
            'page' => 'bus',
            'existing_layouts' => $session->get('mock_layouts')
        ]);
    } //add

    #[Route('/admin/platform/bus-layout/save-ajax', name: 'admin_platform_bus_layout_save_ajax', methods: ['POST'])]
    public function saveAjax(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['name'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }

        // Récupération de l'historique en session
        $layouts = $session->get('mock_layouts', []);

        // Génération d'un ID auto-incrémenté artificiel
        $newId = count($layouts) > 0 ? max(array_keys($layouts)) + 1 : 1;

        // Stockage du gabarit complet conforme aux colonnes de ton MCD
        $layouts[$newId] = [
            'id' => $newId,
            'name' => $data['name'],
            'rows' => (int)$data['rows'],
            'columns' => (int)$data['columns'],
            'aisles' => $data['aisles'], // Tableau JSON ex: ["2"]
            'hasBackExtraSeat' => (bool)$data['hasBackExtraSeat'],
            'matrix' => $data['matrix'] // Les coordonnées individuelles dessinées
        ];

        // Écriture en session
        $session->set('mock_layouts', $layouts);

        return new JsonResponse([
            'success' => true,
            'id' => $newId,
            'name' => $data['name']
        ]);
    } //saveAjax
}
