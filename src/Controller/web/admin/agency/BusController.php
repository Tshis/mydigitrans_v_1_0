<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BusController extends AbstractController
{
    #[Route('/admin/agency/bus/list', name: 'admin_agency_bus_index')]
    public function index(): Response
    {
        return $this->render('admin/agency/bus/index.html.twig', [
            'page' => 'bus',
        ]);
    } //index

}
