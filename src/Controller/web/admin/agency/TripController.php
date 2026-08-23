<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TripController extends AbstractController
{

    #[Route('/admin/agency/trips', name: 'admin_agency_trip_index')]
    public function index(Request $request): Response
    {

        return $this->render('admin/agency/trip/index.html.twig', [
            'page' => 'trip',
        ]);
    } //index

    #[Route('/admin/agency/trip/add', name: 'admin_agency_trip_add')]
    public function add(Request $request): Response
    {

        // Données nécessaires pour remplir le formulaire de planification
        $routes = [
            ['id' => 1, 'code' => 'KIN-KKW', 'name' => 'Kinshasa - Kikwit'],
            ['id' => 2, 'code' => 'KIN-MUA', 'name' => 'Kinshasa - Muanda'],
        ];

        $buses = [
            ['id' => 10, 'plate' => 'A-1234-BC', 'model' => 'Coaster (30 places)', 'capacity' => 30],
            ['id' => 11, 'plate' => 'B-5678-CD', 'model' => 'King Long (50 places)', 'capacity' => 50],
        ];

        $drivers = [
            ['id' => 50, 'name' => 'Jean-Pierre Makila'],
            ['id' => 51, 'name' => 'Dieudonné Mwamba'],
        ];

        return $this->render('admin/agency/trip/add.html.twig', [
            'page' => 'trip',
            'routes' => $routes,
            'buses' => $buses,
            'drivers' => $drivers,
        ]);
    } //add



    #[Route('/admin/agency/trip/show/{code}', name: 'admin_agency_trip_show')]
    public function show(Request $request): Response
    {
        return $this->render('admin/agency/trip/show.html.twig', [
            'page' => 'trip',
        ]);
    } //show

    #[Route('/admin/agency/trip/search/', name: 'admin_agency_trip_search')]
    public function search(Request $request): Response
    {
        $result = true;
        return $this->render('admin/agency/trip/search_result.html.twig', [
            'page' => 'trip',
            'result' => $result
        ]);
    } //search

}
