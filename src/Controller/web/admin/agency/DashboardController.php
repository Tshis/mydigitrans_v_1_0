<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{

    #[Route('/admin/agency/dashboard', name: 'admin_agency_dashboard')]
    public function index(Request $request): Response
    {

        // 1. Simulation de l'utilisateur connecté via ton entité 3. User
        // Modifie 'userBranch' à 'SUC-KIN-01' pour simuler la vue restrictive d'un guichetier !
        $currentUser = [
            'firstname' => 'Daniel',
            'lastname' => 'Lukonu',
            'userBranch' => null, // null = Siège Central / Général
            'agencyId' => 1
        ];

        $isCentral = $currentUser['userBranch'] === null;
        $selectedBranch = $request->query->get('branch_code', '');

        // Liste des succursales (uniquement pour le select du Central)
        $branchesList = [
            ['code' => 'SUC-KIN-01', 'name' => 'Victoire - Rond Point'],
            ['code' => 'SUC-MAT-02', 'name' => 'Matadi Ville - Port']
        ];

        // 2. Hydratation dynamique des statistiques temps réel du tiroir-caisse
        $stats = [
            'revenue' => [
                [
                    'amount' => $isCentral ? 4850000 : 2160000,
                    'currency' => 'CDF'
                ],
                [
                    'amount' => $isCentral ? 1420.00 : 340.00,
                    'currency' => 'USD'
                ]

            ],
            'active_trips' => $isCentral ? 12 : 4,
            'booked_seats' => $isCentral ? 245 : 88,
            'total_seats' => $isCentral ? 360 : 120,
            'broken_buses' => $isCentral ? 2 : 1, // Lié au statut 'broken' de ton atelier
            'expired_docs' => $isCentral ? 4 : 0  // Lié à l'entité BusDocuments
        ];

        // 3. Journal des départs imminents (RN1 / Itinéraires)
        $upcomingTrips = [
            ['time' => '14:30', 'bus' => 'Toyota Coaster', 'plate' => 'A-5678-DE', 'route' => 'Kinshasa ➔ Matadi', 'seats_taken' => 28, 'capacity' => 30, 'status' => 'loading', 'statusLabel' => 'En Embarquement'],
            ['time' => '15:00', 'bus' => 'Mercedes Sprinter', 'plate' => 'A-1234-BC', 'route' => 'Kinshasa ➔ Kikwit', 'seats_taken' => 15, 'capacity' => 15, 'status' => 'ready', 'statusLabel' => 'Prêt au départ']
        ];

        return $this->render('admin/agency/dashboard/index.html.twig', [
            'page' => 'dashboard',
            'isCentral' => $isCentral,
            'current_branch_name' => $isCentral ? 'Siège Central' : 'Victoire - Rond Point',
            'selected_branch' => $selectedBranch,
            'branches_list' => $branchesList,
            'stats' => $stats,
            'upcoming_trips' => $upcomingTrips
        ]);
    } //index
}
