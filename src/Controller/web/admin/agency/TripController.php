<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Dompdf\Dompdf;
use Dompdf\Options;
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


    /**
     * MANIFESTE PDF : COMPILATION ET EXPORT DE LA FEUILLE DE ROUTE VIA DOMPDF
     */
    #[Route('/admin/agency/trips/{code}/manifest-pdf', name: 'admin_agency_trip_manifest_pdf', methods: ['GET'])]
    public function generateManifestPdf(string $code): Response
    {
        // 1. Extraction et compilation des caractéristiques du voyage
        $trip = [
            'id' => 1,
            'code' => $code,
            'tripNumber' => 'TRP-2026-0042',
            'route' => 'Kinshasa - Kikwit via Kenge',
            'departureDate' => new \DateTime('2026-07-14'),
            'departureTime' => '14h30',
            'arrivalTime' => '22h30',
            'bus' => 'N°01 (Mercedes Marcopolo)',
            'plate' => '2432AB01',
            'capacity' => 54,
            'driver' => 'Jean Mukendi',
            'coDriver' => 'Dummy Tshimanga',
            'statusLabel' => 'Embarquement en cours'
        ];

        // 2. Liste des passagers enregistrés (MCD 36/Reservation)
        $passengersList = [
            ['seat' => '01', 'category' => 'VIP', 'name' => 'Kabila Dieudonné', 'route_segment' => 'Kinshasa → Kikwit', 'phone' => '+243 812 345 678', 'status' => 'confirmed'],
            ['seat' => '03', 'category' => 'Std', 'name' => 'Ilunga Christian', 'route_segment' => 'Kenge → Kikwit', 'phone' => '+243 824 556 677', 'status' => 'pending']
        ];

        // 3. Options de base Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        // 4. Rendu du template Twig épuré
        $html = $this->renderView('admin/agency/trip/manifest_pdf.html.twig', [
            'agency_name' => 'TransKin Express', // Simulé via app.user.agency
            'trip' => $trip,
            'passengers_list' => $passengersList
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 5. Capture du flux et retour sécurisé Symfony Response
        $pdfOutput = $dompdf->output();
        $filename = sprintf('Manifeste_%s.pdf', $trip['tripNumber']);

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename)
        ]);
    }
}
