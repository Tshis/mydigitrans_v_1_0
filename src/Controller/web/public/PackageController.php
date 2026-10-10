<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackageController extends AbstractController
{
    #[Route('/public/package', name: 'public_package_index')]
    public function index(): Response
    {
        return $this->render('public/package/index.html.twig', [
            'page' => 'package',
        ]);
    } //index

    /**
     * VÉRIFICATION DES SOUTES ET DEPARTS DISPONIBLES POUR LES COLIS
     */
    #[Route('/public/package/results', name: 'public_package_results')]
    public function results(Request $request): Response
    {

        $searchQuery = [
            'departure' => $request->request->get('departure', 'Kinshasa'),
            'destination' => $request->request->get('destination', 'Matadi'),
            'date' => new \DateTime($request->request->get('date', 'now')),
            'weight' => $request->request->get('weight', '5 – 10 kg')
        ];

        // Simulation des lignes actives avec espace fret disponible en soute
        $availableTrips = [
            [
                'id' => 801,
                'agency' => [
                    'name' => 'TransKin Express',
                    'main_currency' => 'USD'
                ],
                'bus_model' => 'Marcopolo Cargo G7',
                'departure_time' => '07h30',
                'departure_station' => 'Gare Centrale (Gombe)',
                'duration' => '6h 30m',
                'arrival_time' => '14h00',
                'arrival_station' => 'Gare Ville de Matadi',
                'price_per_kg' => 1.20,
                'estimated_fare' => [
                    'min' => 5 * 1.2,
                    'max' => 15 * 1.2

                ] // Estimation selon la grille de poids
            ],
            [
                'id' => 802,
                'agency' => [
                    'name' => 'Océan du Congo',
                    'main_currency' => 'CDF'
                ],
                'bus_model' => 'Scania Streamline',
                'departure_time' => '09h00',
                'departure_station' => 'Poste de Kingabwa',
                'duration' => '7h 00m',
                'arrival_time' => '16h00',
                'arrival_station' => 'Gare du Port (Matadi)',
                'price_per_kg' => 3000,
                'estimated_fare' => [
                    'min' => 5 * 3000,
                    'max' => 15 * 3000

                ] // Estimation selon la grille de poids
            ]
        ];

        return $this->render('public/package/resultats.html.twig', [
            'page' => 'package',
            'search_query' => $searchQuery,
            'available_trips' => $availableTrips
        ]);
    } //results


    /**
     * ACCROCHAGE DE LA SELECTION DE SOUTE COLIS
     */
    #[Route('/public/package/reserver', name: 'public_package_book', methods: ['POST'])]
    public function bookPackage(Request $request): Response
    {
        //Imprimer le bon de reservation et retournez à l'index colis.

        $this->addFlash('success', 'Départ sélectionné. Renseignez à présent l\'identité de l\'expéditeur et du destinataire.');
        return $this->redirectToRoute('public_package_results');
    } //bookPackage

}
