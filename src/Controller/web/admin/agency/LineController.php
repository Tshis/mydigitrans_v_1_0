<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LineController extends AbstractController
{
    #[Route('/admin/agency/lines', name: 'admin_agency_line_index')]
    public function index(): Response
    {
        return $this->render('admin/agency/line/index.html.twig', [
            'page' => 'line',
        ]);
    } //index

    #[Route('/add', name: 'admin_agency_line_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Traitement à venir lorsque nous brancherons la base de données
            $this->addFlash('success', 'Trajet enregistré avec succès (Simulation) !');
            return $this->redirectToRoute('admin_agency_line_index');
        }

        return $this->render('admin/line/add.html.twig', [
            'page' => 'line'
        ]);
    } //add
}
