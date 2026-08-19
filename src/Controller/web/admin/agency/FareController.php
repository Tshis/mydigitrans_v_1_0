<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FareController extends AbstractController
{

    #[Route('/admin/agency/fare/add', name: 'admin_agency_fare_add')]
    #[Route('/admin/agency/line/{slug}/fare/add', name: 'admin_agency_fare_line_add')]
    public function fare_line_add(Request $request, $slug = null): Response
    {
        // 1. Liste pour le Master Selector (Toutes les routes de l'agence)
        $allRoutes = [
            ['id' => 1, 'code' => 'KIN-KKW', 'slug' => 'KIN-KKW', 'name' => 'Kinshasa - Kikwit'],
            ['id' => 2, 'code' => 'KIN-MUA', 'slug' => 'KIN-KKW', 'name' => 'Kinshasa - Muanda'],
            ['id' => 3, 'code' => 'GOM-BUK', 'slug' => 'KIN-KKW', 'name' => 'Goma - Bukavu'],
        ];

        // 2. La Route Actuelle (Simulation Route #1 : Kinshasa - Kikwit)
        $route = [
            'id' => 1,
            'name' => 'Kinshasa - Kikwit',
            'code' => 'KIN-KKW',
            'slug' => 'KIN-KKW',
            'start_city' => 'Kinshasa',
            'end_city' => 'Kikwit',
            'distance' => 525,
            'currency' => 'CDF',
            'direct_amount' => 65000,
            'price_per_kg' => 500,
            'stops' => [
                ['city' => 'Kinshasa'],
                ['city' => 'Kenge'],
                ['city' => 'Masi-Manimba'],
                ['city' => 'Kikwit']
            ]
        ];

        // 3. Les segments générés (Combinaisons de la ligne RN1)
        $segments = [
            [
                'from_city' => 'Kinshasa',
                'to_id' => 2,
                'to_city' => 'Kenge',
                'distance' => 200,
                'current_amount' => 25000,
                'current_fret' => 200
            ],
            [
                'from_city' => 'Kinshasa',
                'to_id' => 3,
                'to_city' => 'Masi-Manimba',
                'distance' => 350,
                'current_amount' => 45000,
                'current_fret' => 350
            ],
            [
                'from_city' => 'Kenge',
                'to_id' => 3,
                'to_city' => 'Masi-Manimba',
                'distance' => 150,
                'current_amount' => 20000,
                'current_fret' => 150
            ],
            [
                'from_city' => 'Kenge',
                'to_id' => 4,
                'to_city' => 'Kikwit',
                'distance' => 325,
                'current_amount' => 40000,
                'current_fret' => 300
            ],
            [
                'from_city' => 'Masi-Manimba',
                'to_id' => 4,
                'to_city' => 'Kikwit',
                'distance' => 175,
                'current_amount' => 20000,
                'current_fret' => 150
            ],
        ];

        return $this->render('admin/agency/caisse/add.html.twig', [
            'page' => 'fare',
            'slug' => $slug,
            'route' => $route,
            'all_routes' => $allRoutes, // Attention : PHP est sensible à la casse ($allRoutes vs $all_routes)
            'segments' => $segments,
            'active_currencies' => [
                ['code' => 'CDF'],
                ['code' => 'USD']
            ]
        ]);
    } //fare_line_add
}
