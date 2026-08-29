<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MaintenanceController extends AbstractController
{
    #[Route('/admin/agency/maintenance/list', name: 'admin_agency_maintenance_index')]
    public function index(): Response
    {

        // Données simulées de l'historique de l'Atelier Central
        // On respecte tes deux états : 'resolved' et 'broken'
        $fleetLogs = [
            [
                'brand' => 'Mercedes-Benz',
                'model' => 'Sprinter 316',
                'plateNumber' => 'A-1234-BC',
                'reportedAt' => new \DateTime('2026-08-20'),
                'issue' => 'Surchauffe moteur sur la route de Kenge',
                'solution' => 'Remplacement du joint de culasse et purge du radiateur',
                'resolvedAt' => new \DateTime('2026-08-24'),
                'status' => 'resolved',
                'statusLabel' => 'Résolu',
                'code' => 'bus-001'
            ],
            [
                'brand' => 'Toyota',
                'model' => 'Coaster',
                'plateNumber' => 'A-5678-DE',
                'reportedAt' => new \DateTime('2026-08-28'),
                'issue' => 'Réchauffement du radiateur et fuite de liquide',
                'solution' => null, // Pas encore de solution
                'resolvedAt' => null,
                'status' => 'broken',
                'statusLabel' => 'Panne',
                'code' => 'bus-002'
            ],
            [
                'brand' => 'Scania',
                'model' => 'K410',
                'plateNumber' => 'A-9012-FG',
                'reportedAt' => new \DateTime('2026-08-15'),
                'issue' => 'Amortisseurs arrières usés sur la RN1',
                'solution' => 'Changement complet des kits de suspension arrière',
                'resolvedAt' => new \DateTime('2026-08-18'),
                'status' => 'resolved',
                'statusLabel' => 'Résolu',
                'code' => 'bus-003'
            ]
        ];

        return $this->render('admin/agency/maintenance/index.html.twig', [
            'page' => 'maintenance',
            'fleet' => $fleetLogs // Envoi du tableau au Twig
        ]);
    } //index

    #[Route('/admin/agency/maintenance/declare', name: 'admin_agency_maintenance_declare')]
    public function declare(Request $request): Response
    {


        // Simulation des véhicules de l'agence pour le select
        $activeBuses = [
            ['code' => 'bus-001', 'brand' => 'Mercedes-Benz', 'model' => 'Sprinter', 'plateNumber' => 'A-1234-BC'],
            ['code' => 'bus-002', 'brand' => 'Toyota', 'model' => 'Coaster', 'plateNumber' => 'A-5678-DE'],
            ['code' => 'bus-003', 'brand' => 'Scanya', 'model' => 'K410', 'plateNumber' => 'A-9012-FG'],
        ];

        // Traitement de la soumission
        if ($request->isMethod('POST')) {
            $busCode = $request->request->get('bus_code');
            $issue = $request->request->get('issue_description');

            // Flash message simulant la mise en panne automatique
            $this->addFlash('success', sprintf(
                'Panne enregistrée avec succès pour le véhicule %s. Statut basculé en BROKEN.',
                strtoupper($busCode)
            ));

            return $this->redirectToRoute('admin_agency_maintenance_index');
        }



        return $this->render('admin/agency/maintenance/declare.html.twig', [
            'page' => 'maintenance',
            'active_buses' => $activeBuses
        ]);
    } //declare


    #[Route('/admin/agency/maintenance/{bus}/resolve', name: 'admin_agency_maintenance_resolve')]
    public function resolve(int $bus, Request $request): Response
    {
        // 1. Simulation du Véhicule (bus) lié au ticket d'incident
        $bus = [
            'brand' => 'Toyota',
            'model' => 'Coaster',
            'plateNumber' => 'A-5678-DE',
            'capacity' => 30,
            'mileage' => 89400,
            'currency' => 'CDF' // Devise appelée par ton tableau : {{ bus.currency }}
        ];

        // 2. Simulation de l'Incident courant (log) à clore
        // Le statut de départ est impérativement 'broken'
        $log = [
            'id' => $bus,
            'issue' => 'Réchauffement du radiateur et fuite de liquide sur la RN1',
            'status' => 'broken',
            'reportedAt' => new \DateTime('2026-08-25')
        ];

        // 3. Traitement de la clôture lors de la soumission du formulaire
        if ($request->isMethod('POST')) {
            // Récupération des données de ton formulaire (solution, mécanicien, date)
            $solutionDescription = $request->request->get('solution_description');
            $updatedBy = $request->request->get('updated_by');
            $resolvedAt = $request->request->get('resolved_at');

            // Ici, ton code Doctrine fera basculer le statut à 'resolved' en base de données
            $this->addFlash('success', sprintf(
                'La panne du bus %s a été résolue par %s. Le véhicule repasse au statut RESOLVED.',
                $bus['plateNumber'],
                $updatedBy
            ));

            // Redirection vers le registre central de l'Atelier
            return $this->redirectToRoute('admin_agency_maintenance_index');
        }

        // 4. Envoi des variables exactes requises par ton architecture HTML Twig
        return $this->render('admin/agency/maintenance/resolve.html.twig', [
            'page' => 'maintenance',
            'bus' => $bus,
            'log' => $log
        ]);
    } //resolve

    #[Route('/admin/agency/maintenance/{bus}/cancel', name: 'admin_agency_maintenance_cancel')]
    public function cancel(int $bus, Request $request): Response
    {
        return $this->redirectToRoute('admin_agency_maintenance_index');
    } //cancel

}
