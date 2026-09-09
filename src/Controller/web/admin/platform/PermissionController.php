<?php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PermissionController extends AbstractController
{
    #[Route('/admin/agency/permission/role/{code}/permissions', name: 'admin_agency_permission_manage')]
    public function managePermissions(string $code, Request $request): Response
    {
        // 1. Simulation du rôle sélectionné par l'agence (MCD 4)
        $role = [
            'code' => $code,
            'name' => 'Caissier Billetterie',
            'description' => 'Guichetier en charge des réservations de places et des ventes de billets physiques.'
        ];

        // 2. Simulation de la liste des permissions disponibles pour MyDigitrans [MCD 6 & 7]
        $availablePermissions = [
            [
                'code' => 'ticket.view',
                'name' => 'Lecture Ticket',
                'module' => 'ticket',
                'description' => 'Permet de voir la liste et le détail des tickets vendus.',
                'isAssigned' => true // Déjà coché en BDD pour ce rôle
            ],
            [
                'code' => 'ticket.create',
                'name' => 'Émission Ticket',
                'module' => 'ticket',
                'description' => 'Autorise la création et l\'impression de nouveaux titres de transport.',
                'isAssigned' => true
            ],
            [
                'code' => 'ticket.cancel',
                'name' => 'Annulation / Remboursement',
                'module' => 'ticket',
                'description' => 'Droit de révoquer un billet et de générer un avoir ou remboursement.',
                'isAssigned' => false // Décoché par défaut
            ],
            [
                'code' => 'trip.view',
                'name' => 'Consulter les voyages',
                'module' => 'trip',
                'description' => 'Permet de voir la grille des départs programmés de l\'agence.',
                'isAssigned' => true
            ],
            [
                'code' => 'caisse.flux.view',
                'name' => 'Consulter les flux monétaires',
                'module' => 'payment',
                'description' => 'Voir le montant total encaissé sur sa session de travail.',
                'isAssigned' => false
            ]
        ];

        if ($request->isMethod('POST')) {
            // Récupération des cases cochées : $request->request->all('permissions')
            $this->addFlash('success', sprintf('La matrice des droits pour le rôle %s a été sauvegardée.', $role['name']));
            return $this->redirectToRoute('admin_agency_user_index');
        }

        return $this->render('admin/agency/permission/permission.html.twig', [
            'page' => 'user_management',
            'role' => $role,
            'available_permissions' => $availablePermissions
        ]);
    } //managePermissions



    #[Route('/admin/platform/permission/create', name: 'admin_platform_permission_create')]
    #[Route('/admin/platform/permission/{code}/create', name: 'admin_platform_permission_edit')]
    public function edit_and_create(Request $request, ?string $code = null): Response
    {
        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $name = $request->request->get('name');

            // Sauvegarde de ta nouvelle entité technique [MCD 6]
            $this->addFlash('success', sprintf('La permission globale [%s : %s] a été injectée dans le cœur du SaaS MyDigitrans.', $code, $name));

            return $this->redirectToRoute('admin_agency_user_index'); // Redirection de test
        }

        return $this->render('admin/platform/permission/form.html.twig', [
            'page' => 'platform_security'
        ]);
    } //edit_and_create
}
