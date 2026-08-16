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


    #[Route('/admin/agency/shipment/add', name: 'admin_agency_shipment_add')]
    public function add(): Response
    {


        // Configuration par défaut de l'agence connectée
        $systemCurrency = 'USD';
        $pricePerKg = 1.25; // 1.25 $ par Kilo pour la ligne active

        $currentBranch = [
            'id' => 1,
            'name' => 'Kinshasa (Gare Centrale Siège)'
        ];

        // Succursales de destination du réseau (MCD Table 4)
        $branches = [
            ['id' => 2, 'name' => 'Succursale Goma'],
            ['id' => 3, 'name' => 'Succursale Matadi'],
            ['id' => 4, 'name' => 'Succursale Kikwit Terminus'],
        ];

        // Liste des devises actives pour fixer manuellement le prix par pièce
        $activeCurrencies = [
            ['code' => 'USD'],
            ['code' => 'CDF'],
        ];



        return $this->render('admin/agency/shipment/add.html.twig', [
            'page' => 'shipment',
            'system_currency'   => $systemCurrency,
            'price_per_kg'      => $pricePerKg,
            'current_branch'    => $currentBranch,
            'branches'          => $branches,
            'active_currencies' => $activeCurrencies,

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
