<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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


    #[Route('/admin/agency/line/add', name: 'admin_agency_line_add')]
    public function add(): Response
    {
        // Devises autorisées pour le calcul de fret sur le réseau Mydigitrans
        $activeCurrencies = [
            ['code' => 'CDF'],
            ['code' => 'USD'],
        ];

        return $this->render('admin/agency/line/add.html.twig', [
            'page' => 'line',
            'active_currencies' => $activeCurrencies,
        ]);
    } //add


    #[Route('/admin/agency/line/{slug}/details', name: 'admin_agency_line_show')]
    public function show(): Response
    {


        return $this->render('admin/agency/line/show.html.twig', [
            'page' => 'line',
        ]);
    } //add
}
