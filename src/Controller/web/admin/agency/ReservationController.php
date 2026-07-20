<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{

    #[Route('/admin/agency/reservations', name: 'admin_agency_reservation_index')]
    public function index(Request $request): Response
    {
        return $this->render('admin/agency/reservation/index.html.twig', [
            'page' => 'reservation',
        ]);
    } //index





}
