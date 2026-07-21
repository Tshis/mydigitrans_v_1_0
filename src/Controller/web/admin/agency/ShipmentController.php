<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShipmentController extends AbstractController
{

    #[Route('/admin/agency/shipments', name: 'admin_agency_shipment_index')]
    public function index(Request $request): Response
    {
        return $this->render('admin/agency/shipment/index.html.twig', [
            'page' => 'shipment',
        ]);
    } //index


    // src/Controller/Admin/ShipmentController.php
    #[Route('/admin/agency/shipment/add', name: 'admin_agency_shipment_add')]
    public function add(Request $request): Response
    {
        // Simulation : Devise par défaut de la session de l'agent
        $currencyCode = 'CDF';

        // Grille tarifaire au Kilo : "id_depart-id_arrivee" => Prix en CDF par Kg
        $weightPricingMatrix = [
            '1-3' => 500, // Kinshasa -> Kikwit : 500 CDF / Kg
            '1-2' => 300  // Kinshasa -> Kenge  : 300 CDF / Kg
        ];

        // Forfaits fixes par pièce (Si l'agent choisit une tarification forfaitaire)
        $unitPackageFares = [
            'box_small'  => 5000,   // Petit colis / paquet
            'box_large'  => 15000,  // Gros carton / sac
            'tv_plasma'  => 25000,  // Électronique majeur
            'motorbike'  => 85000   // Engin / Moto
        ];

        // Surcharges de sécurité selon la nature de la marchandise
        $natureSurcharges = [
            'standard'   => 0,
            'fragile'    => 5000,  // +5 000 CDF
            'perishable' => 3000   // +3 000 CDF
        ];

        return $this->render('admin/agency/shipment/add.html.twig', [
            'page'              => 'shipment',
            'currency_code'     => $currencyCode,
            'weight_matrix'     => $weightPricingMatrix,
            'unit_fares'        => $unitPackageFares,
            'nature_surcharges' => $natureSurcharges
        ]);
    } //add


    #[Route('/admin/agency/shipment/{reference}', name: 'admin_agency_shipment_show')]
    public function show(Request $request): Response
    {
        return $this->render('admin/agency/shipment/show.html.twig', [
            'page' => 'shipment',
        ]);
    } //show

}
