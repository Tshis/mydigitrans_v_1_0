<?php

namespace App\Controller\web\admin\agency;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class RoleController extends AbstractController
{

    #[Route('/admin/agency/roles', name: 'admin_agency_role_index')]
    public function index(): Response
    {
        // Simulation du registre des fiches de postes de l'agence [MCD 4]
        $roles = [
            ['name' => 'Super Admin Agence', 'code' => 'ROLE_AGENCY_ADMIN', 'description' => 'Gestion totale de la compagnie, des gares et de la flotte.', 'scope' => 'platform', 'isActive' => true],
            ['name' => 'Guichetier de Nuit', 'code' => 'ROLE_AGENCY_GUICHETIER_DE_NUIT', 'description' => 'Encaissement des billets sur la tranche de nuit.', 'scope' => 'agency', 'isActive' => true],
            ['name' => 'Percepteur Fret / Colis', 'code' => 'ROLE_AGENCY_PERCEPTEUR_FRET', 'description' => 'Supervision de la soute et pesage messagerie.', 'scope' => 'agency', 'isActive' => false]
        ];

        return $this->render('admin/agency/role/index.html.twig', [
            'page' => 'job title',
            'roles' => $roles
        ]);
    } //index

    #[Route('/admin/agency/role/add', name: 'admin_agency_role_add')]
    #[Route('/admin/agency/role/{code}/edit', name: 'admin_agency_role_edit')]
    public function add_and_edit(Request $request, ?string $code = null): Response
    {
        $isEdit = $code !== null;
        $role = null;

        // Liste fixe des segments fonctionnels de MyDigitrans
        $modules = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge', 'value' => 'read', 'description' => 'Vue d\'ensemble des statistiques de la journée.'],
            ['key' => 'branches', 'label' => 'Branch (Succursales)', 'icon' => 'fa-folder-tree', 'value' => 'none', 'description' => 'Consulter ou modifier les fiches des succursales.'],
            ['key' => 'bus', 'label' => 'Bus', 'icon' => 'fa-bus-simple', 'value' => 'read', 'description' => 'Gestion de la flotte automobile et assignations.'],
            ['key' => 'routes', 'label' => 'Trajets (Lignes)', 'icon' => 'fa-route', 'value' => 'read_write', 'description' => 'Configuration des lignes et planification.'],
            ['key' => 'reservations', 'label' => 'Réservations', 'icon' => 'fa-file-circle-check', 'value' => 'read', 'description' => 'Validation des billets passagers.'],
            ['key' => 'cargo', 'label' => 'Colis (Fret)', 'icon' => 'fa-box-open', 'value' => 'write', 'description' => 'Enregistrement et suivi des colis.']
        ];

        if ($isEdit) {
            $role = [
                'code' => $code,
                'name' => 'Guichetier de Nuit',
                'description' => 'Encaissement des billets sur la tranche de nuit.',
                'isActive' => true
            ];
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $this->addFlash('success', $isEdit ? sprintf('Le poste "%s" a été mis à jour.', $name) : sprintf('Le poste "%s" a été déployé.', $name));
            return $this->redirectToRoute('admin_agency_role_index');
        }

        return $this->render('admin/agency/role/form.html.twig', [
            'page' => 'job title',
            'isEdit' => $isEdit,
            'role' => $role,
            'modules' => $modules
        ]);
    } //add_and_edit



}
