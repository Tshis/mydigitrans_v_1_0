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


    #[Route('/admin/agency/reservation/add/{code}', name: 'admin_agency_reservation_add')]
    public function add(Request $request, BusLayoutGridBuilder $busLayoutGridBuilder, string $code): Response
    {
        $session = $request->getSession();
        $layouts = $session->get('bus_layout', []);

        // Simulation : l'ID du layout dépend du code du voyage / véhicule
        $layoutId = 1;
        $busLayout = $layouts[$layoutId] ?? null;

        if (!$busLayout) {
            throw $this->createNotFoundException('Bus layout introuvable');
        }

        // --- SIMULATION DES TARIFS PROVENANT DU MCD ---

        // La devise peut changer dynamiquement selon le trajet ou la session d'agence
        $currencyCode = 'CDF'; // Exemples : 'CDF', 'USD', 'XAF'

        // Grille tarifaire par segment : "id_depart-id_arrivee" => Prix en CDF
        $pricingMatrix = [
            '1-3' => 45000, // Kinshasa -> Kikwit (Complet)
            '1-2' => 25000, // Kinshasa -> Kenge (Partiel)
            '2-3' => 30000  // Kenge -> Kikwit (Partiel)
        ];

        // Prix par catégorie de place
        $fareCategories = [
            'seat' => 0,      // Pas de surplus pour un siège Standard
            'vip'  => 15000   // +15 000 CDF de majoration pour la classe VIP
        ];

        return $this->render('admin/agency/reservation/add.html.twig', [
            'page' => 'reservation',
            'trip_code' => $code,
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
            'pricing_matrix' => $pricingMatrix,
            'fare_categories' => $fareCategories,
            'currency_code' => $currencyCode,
        ]);
    } //add

    #[Route('/admin/agency/reservation/{reference}', name: 'admin_agency_reservation_show')]
    public function show(Request $request): Response
    {
        return $this->render('admin/agency/reservation/show.html.twig', [
            'page' => 'reservation',
        ]);
    } //show

}
