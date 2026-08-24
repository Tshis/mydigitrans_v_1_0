<?php

namespace App\Controller\web\admin\agency;

use App\Service\BusLayoutGridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BusController extends AbstractController
{
    #[Route('/admin/agency/bus/list', name: 'admin_agency_bus_index')]
    public function index(): Response
    {

        $fleet = [
            [
                'brand' => 'Mercedes-Benz',
                'model' => 'Sprinter 316',
                'type' => 'Minibus',
                'plateNumber' => 'A-1234-BC',
                'capacity' => 19,
                'mileage' => 142050,
                'docStatus' => 'up-to-date',
                'status' => 'available',
                'statusLabel' => 'Disponible'
            ],
            [
                'brand' => 'Toyota',
                'model' => 'Coaster',
                'type' => 'Bus Interurbain',
                'plateNumber' => 'A-5678-DE',
                'capacity' => 30,
                'mileage' => 89400,
                'docStatus' => 'up-to-date',
                'status' => 'on_road',
                'statusLabel' => 'En Voyage'
            ],
            [
                'brand' => 'Scania',
                'model' => 'K410',
                'type' => 'Autocar Grand Confort',
                'plateNumber' => 'A-9012-FG',
                'capacity' => 54,
                'mileage' => 310200,
                'docStatus' => 'expired',
                'status' => 'broken',
                'statusLabel' => 'En Panne'
            ]
        ];




        return $this->render('admin/agency/bus/index.html.twig', [
            'page' => 'bus',
            'fleet' => $fleet
        ]);
    } //index

    #[Route('/admin/agency/bus/add', name: 'admin_agency_bus_add')]
    public function add(): Response
    {
        return $this->render('admin/agency/bus/add.html.twig', [
            'page' => 'bus',
        ]);
    } //add


    #[Route('/admin/agency/bus/{code}/details', name: 'admin_agency_bus_show')]
    public function show(string $code, Request $request, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {
        $session = $request->getSession();

        $layouts = $session->get('bus_layout', []);

        // simulation bus -> layout
        $busToLayoutMap = [
            'bus-001' => 1,
            'bus-002' => 2,
            'bus-003' => 3,
        ];

        $layoutId = $busToLayoutMap[$code] ?? null;

        $busLayout = $layouts[$layoutId] ?? null;

        if (!$busLayout) {
            throw $this->createNotFoundException('Bus layout introuvable');
        }


        return $this->render('admin/agency/bus/show.html.twig', [
            'page' => 'bus',
            'bus_code' => $code,
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
        ]);
    } ////show()


}
