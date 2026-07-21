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
    public function add(Request $request): Response
    {
        return $this->render('admin/agency/shipment/add.html.twig', [
            'page' => 'shipment',
        ]);
    } //add

}
