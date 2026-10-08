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


    /**
     * SÉQUENCE INTERACTION : TRAITEMENT DE LA RECHERCHE ET RENDU DES RÉSULTATS DISPONIBLES 
     */
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

    /**
     * TUNNEL RESERVATION ÉTAPE 1 : Coordonnées du Passager & Siège
     */
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

        if ($request->isMethod('post')) {

            //enregistrement de la reservation avec le status pending


            $reservation = [
                'reference' => 'RSV-001-2026',
                //.......
            ];

            //redirection vers la 2e etape Lorsque le paiement en ligne sera dispo
            // return $this->redirectToRoute('public_lines_booking_step_2', ['reference' => $reservation['reference']]);
        }


        // Extraction du voyage concerné [Profile]
        $trip = [
            'id' => 101,
            'agency_name' => 'TransKin Express',
            'departure' => 'Kinshasa',
            'destination' => 'Kikwit',
            'date' => new \DateTime('+2 days'),
            'departure_time' => '14h30',
            'price' => 45000,
            'currency_code' => 'CDF'
        ];

        return $this->render('public/lines/booking.html.twig', [
            'page' => 'lines',
            'trip' => $trip,
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
        ]);
    } //booking


    /**
     * TUNNEL RESERVATION ÉTAPE 2 : SÉLECTION DU MODE DE RÈGLEMENT
     */
    #[Route('/public/lines/booking/{reference}/step-2', name: 'public_lines_booking_step_2')]
    public function step_2(string $reference): Response
    {
        $booking = [
            'id' => 112,
            'reference' => 'CODE-TRIP-001',
            'passenger_name' => 'Kabila Dieudonné',
            'departure' => 'Kinshasa',
            'destination' => 'Kikwit',
            'bus_model' => 'Mercedes Marcopolo (VIP)',
            'seat_number' => '01',
            'price' => 45.00,
            'currency_code' => 'USD'
        ];

        return $this->render('public/lines/booking_step_2.html.twig', [
            'page' => 'lines',
            'booking' => $booking
        ]);
    }//step_2

    /**
     * STEP 3 : FINALISATION DU TRAJET : ROUTAGE SELON LE MODE CHOISI
     */
    #[Route('/public/lines/booking/{reference}/finaliser', name: 'public_lines_booking_finalize')]
    public function finalize(Request $request): Response
    {
        $method = $request->request->get('payment_method');

        if ($method === 'station_cash') {
            $this->addFlash('warning', 'Votre réservation au guichet a été enregistrée. Veuillez la régler sous 2 heures.');
            // Route Symfony d'impression Dompdf du bon de réservation que nous avons codé ensemble
            return $this->redirectToRoute('admin_agency_reservation_print_pdf', ['id' => 42]);
        }

        // Sinon : Routage vers l'API de l'opérateur Mobile Money sélectionné
        $this->addFlash('success', 'Billet électronique payé et validé avec succès.');
        return $this->redirectToRoute('public_lines_index');
    } //finalize


}
