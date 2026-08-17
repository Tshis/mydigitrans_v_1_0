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
    public function show(string $slug): Response
    {
        // 1. Simulation des données consolidées de la Route (MCD 11) et de ses Arrêts (MCD 12)
        $route = [
            'id' => 1,
            'name' => 'Kinshasa - Muanda',
            'code' => 'KIN-MUA',
            'slug' => $slug,
            'distance' => 650,
            'duration' => '11h30',
            'pricePerKg' => 500,
            'currency' => 'CDF',
            'isAvailable' => true,
            'reservationExpirationDelay' => 120,
            'createdAt' => new \DateTime('2026-01-15'),
            'createdBy' => 'Daniel Lukonu',
            'stops' => [
                [
                    'id' => 101, // ID ajouté pour l'option 1
                    'stopOrder' => 1,
                    'city' => 'Kinshasa',
                    'country' => 'RDC',
                    'distanceFromDeparture' => 0,
                    'durationFromDeparture' => '0h00',
                    'isActive' => true
                ],
                [
                    'id' => 102, // ID ajouté
                    'stopOrder' => 2,
                    'city' => 'Mbanza-Ngungu',
                    'country' => 'RDC',
                    'distanceFromDeparture' => 150,
                    'durationFromDeparture' => '2h30',
                    'isActive' => true
                ],
                [
                    'id' => 103, // ID ajouté
                    'stopOrder' => 3,
                    'city' => 'Matadi',
                    'country' => 'RDC',
                    'distanceFromDeparture' => 350,
                    'durationFromDeparture' => '6h00',
                    'isActive' => true
                ],
                [
                    'id' => 104, // ID ajouté
                    'stopOrder' => 4,
                    'city' => 'Muanda',
                    'country' => 'RDC',
                    'distanceFromDeparture' => 650,
                    'durationFromDeparture' => '11h30',
                    'isActive' => true
                ]
            ]
        ];

        // 2. Simulation des tarifs par segments pour la ligne (MCD 17 - Fare)
        $route['fares'] = [
            [
                'id' => 501, // ID ajouté pour l'option 1
                'departureCity' => 'Kinshasa',
                'arrivalCity' => 'Kisantu',
                'type' => 'Standard',
                'amount' => 15000,
                'pricePerKg' => 200,
                'currency' => 'CDF',
                'validFrom' => new \DateTime('2026-01-01')
            ],
            [
                'id' => 502, // ID ajouté
                'departureCity' => 'Kinshasa',
                'arrivalCity' => 'Matadi',
                'type' => 'Standard',
                'amount' => 35000,
                'pricePerKg' => 400,
                'currency' => 'CDF',
                'validFrom' => new \DateTime('2026-01-01')
            ],
            [
                'id' => 503, // ID ajouté
                'departureCity' => 'Kinshasa',
                'arrivalCity' => 'Muanda',
                'type' => 'VIP',
                'amount' => 65000,
                'pricePerKg' => 500,
                'currency' => 'CDF',
                'validFrom' => new \DateTime('2026-01-01')
            ]
        ];

        return $this->render('admin/agency/line/show.html.twig', [
            'page' => 'line',
            'route' => $route
        ]);
    }
}
