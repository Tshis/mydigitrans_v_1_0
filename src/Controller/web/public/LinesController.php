<?php

namespace App\Controller\web\public;

use App\Service\BusLayoutGridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LinesController extends AbstractController
{
    #[Route('/public/lines', name: 'public_lines_index')]
    public function index(): Response
    {
        return $this->render('public/lines/index.html.twig', [
            'page' => 'lines',
        ]);
    } //index

    #[Route('/public/lines/results', name: 'public_lines_results')]
    public function results(Request $request): Response
    {
        // Extraction des critères métiers saisis par le client (V1.0 multitenant)
        $searchQuery = [
            'departure' => $request->request->get('departure', 'Kinshasa'),
            'destination' => $request->request->get('destination', 'Kikwit'),
            'date' => new \DateTime($request->request->get('date', 'now')),
            'passengers' => (int) $request->request->get('passengers', 1)
        ];

        // Compilation des résultats réels croisant tes grilles tarifaires d'agences [Profile]
        $tripsResults = [
            [
                'id' => 42,
                'agency_name' => 'TransKin Express',
                'bus_model' => 'Mercedes Marcopolo (VIP)',
                'departure_time' => '14h30',
                'slug' => 'kin-matadi-trans-david-01',
                'departure_station' => 'Gare Centrale (Gombe)',
                'duration' => '8h 00m',
                'arrival_time' => '22h30',
                'arrival_station' => 'Terminus Kikwit',
                'seats_available' => 14,
                'price' => 4500,
                'currency_code' => 'CDF'
            ],
            [
                'id' => 43,
                'agency_name' => 'Océan du Congo',
                'slug' => 'kin-matadi-trans-david-01',
                'bus_model' => 'Scania Standard',
                'departure_time' => '15h00',
                'departure_station' => 'Aéroport N\'djili (Kinshasa)',
                'duration' => '8h 30m',
                'arrival_time' => '23h30',
                'arrival_station' => 'Grand Boulevard Kikwit',
                'seats_available' => 38,
                'price' => 3500,
                'currency_code' => 'CDF'
            ]
        ];

        return $this->render('public/lines/results.html.twig', [
            'page' => 'lines',
            'search_query' => $searchQuery,
            'trips_results' => $tripsResults
        ]);
    } //results

    #[Route('/public/lines/{slug}/booking', name: 'public_lines_booking')]
    public function booking(string $slug, Request $request, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {
        $session = $request->getSession();

        $layouts = $session->get('bus_layout', []);

        // simulation bus -> layout
        $busToLayoutMap = [
            'kin-matadi-trans-david-01' => 1,
            'kin-matadi-trans-david-02' => 2,
            'kin-matadi-trans-david-03' => 3,
        ];

        $layoutId = $busToLayoutMap[$slug] ?? null;

        $busLayout = $layouts[$layoutId] ?? null;

        if (!$busLayout) {
            throw $this->createNotFoundException('Bus layout introuvable');
        }

        return $this->render('public/lines/booking.html.twig', [
            'page' => 'lines',
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
        ]);
    } //booking



    /**
     * SÉQUENCE INTERACTION : TRAITEMENT DE LA RECHERCHE ET RENDU DES RÉSULTATS DISPONIBLES [Profile]
     */


    /**
     * INITIALISATION DU TUNNEL DE RÉSERVATION PUBLIC
     */
    #[Route('/reservation/initiate/{id}', name: 'public_booking_initiate', methods: ['POST'])]
    public function initiateBooking(int $id, Request $request): Response
    {
        // Redirection chirurgicale vers l'étape de saisie des coordonnées voyageurs du site public
        return $this->redirectToRoute('public_lines_search');
    }
}
