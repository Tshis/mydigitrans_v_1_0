<?php

namespace App\Controller\web\admin\agency;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class RoleController extends AbstractController
{


    #[Route('/admin/agency/job/add', name: 'admin_agency_job_add', methods: ['GET', 'POST'])]
    #[Route('/admin/agency/job/{code}/edit', name: 'admin_agency_job_edit', methods: ['GET', 'POST'])]
    public function form(Request $request, ?string $code = null): Response
    {
        $isEdit = $code !== null;
        $role = null;

        // Ta liste de segments métiers exacte passée au tableau radio
        $modules = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge', 'value' => 'read', 'description' => 'Vue d\'ensemble des statistiques de vente de la journée.'],
            ['key' => 'branches', 'label' => 'Branch (Succursales)', 'icon' => 'fa-folder-tree', 'value' => 'none', 'description' => 'Consulter ou modifier les fiches des succursales.'],
            ['key' => 'agents', 'label' => 'Agents (Utilisateurs)', 'icon' => 'fa-users', 'value' => 'none', 'description' => 'Gestion des comptes du personnel et des fiches.'],
            ['key' => 'bus', 'label' => 'Bus', 'icon' => 'fa-bus-simple', 'value' => 'read', 'description' => 'Gestion de la flotte automobile et assignations.'],
            ['key' => 'routes', 'label' => 'Trajets (Lignes)', 'icon' => 'fa-route', 'value' => 'read_write', 'description' => 'Configuration des lignes et planification.'],
            ['key' => 'reservations', 'label' => 'Réservations', 'icon' => 'fa-file-circle-check', 'value' => 'read', 'description' => 'Validation des billets passagers.'],
            ['key' => 'cargo', 'label' => 'Colis (Fret)', 'icon' => 'fa-box-open', 'value' => 'write', 'description' => 'Enregistrement et suivi des colis.']
        ];

        if ($isEdit) {
            // Extraction fictive de ton entité Role [MCD 4] pour l'édition
            $role = [
                'code' => $code,
                'name' => 'Guichetier de Nuit',
                'description' => 'En charge des guichets sur la tranche horaire 22h - 6h.'
            ];
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $submittedPerms = $request->request->all('perms'); // Intercepte la matrice radio

            $this->addFlash(
                'success',
                $isEdit
                    ? sprintf('La fonction "%s" et ses privilèges ont été mis à jour.', $name)
                    : sprintf('La fonction "%s" a été créée et ajoutée à l\'organigramme.', $name)
            );

            return $this->redirectToRoute('admin_agency_user_index');
        }

        return $this->render('admin/agency/job/form.html.twig', [
            'isEdit' => $isEdit,
            'role' => $role,
            'modules' => $modules
        ]);
    } //form

}
