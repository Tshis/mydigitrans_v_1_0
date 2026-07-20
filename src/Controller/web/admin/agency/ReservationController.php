<?php

namespace App\Controller\web\admin\agency;

use App\Service\BusLayoutGridBuilder;
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

    #[Route('/admin/agency/reservations/add', name: 'admin_agency_reservation_add')]
    public function add(Request $request, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {
        $session = $request->getSession();

        $layouts = $session->get('bus_layout', []);

        // simulation bus -> layout
        $busToLayoutMap = [
            'kin-matadi-trans-david-01' => 1,
            'kin-matadi-trans-david-02' => 2,
            'kin-matadi-trans-david-03' => 3,
        ];

        $layoutId = 1;

        $busLayout = $layouts[$layoutId] ?? null;

        if (!$busLayout) {
            throw $this->createNotFoundException('Bus layout introuvable');
        }

        return $this->render('admin/agency/reservation/add.html.twig', [
            'page' => 'reservation',
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
        ]);
    } //index





}
