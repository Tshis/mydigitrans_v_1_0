<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{

    #[Route('/admin/agency/agents', name: 'admin_agency_agent_index')]
    public function index(): Response
    {
        // Simulation des utilisateurs rattachés à l'agence courante [MCD 3, 4, 5]
        $users = [
            [
                'firstname' => 'Daniel',
                'lastname' => 'Lukonu',
                'phone' => '+243 812 345 678',
                'email' => 'd.lukonu@mydigitrans.cd',
                'userType' => 'agency',
                'isActive' => true,
                'isOnline' => true,
                'lastLoginAt' => new \DateTime('now'),
                'branchName' => null, // Administrateur général (Siège)
                'branchCode' => null,
                'code' => 'agt-512',
                'roles' => [
                    ['code' => 'ROLE_AGENCY_ADMIN', 'description' => 'Gestion totale de l\'agence']
                ]
            ],
            [
                'firstname' => 'Antoinette',
                'lastname' => 'Mputu',
                'phone' => '+243 897 112 233',
                'email' => 'a.mputu@mydigitrans.cd',
                'userType' => 'agency',
                'isActive' => true,
                'isOnline' => false,
                'lastLoginAt' => new \DateTime('-1 day'),
                'branchName' => 'Victoire - Rond Point',
                'branchCode' => 'SUC-KIN-01',
                'code' => 'agt-510',
                'roles' => [
                    ['code' => 'ROLE_CASHIER', 'description' => 'Vente de billets et encaissement colis']
                ]
            ],
            [
                'firstname' => 'Alphonse',
                'lastname' => 'Kabeya',
                'phone' => '+243 824 556 778',
                'email' => 'a.kabeya@mydigitrans.cd',
                'userType' => 'partner', // Partenaire externe (ex: sous-traitant logistique)
                'isActive' => false, // Compte révoqué ou bloqué
                'isOnline' => false,
                'lastLoginAt' => null,
                'branchName' => 'Matadi Ville - Port',
                'branchCode' => 'SUC-MAT-02',
                'code' => 'agt-513',
                'roles' => [
                    ['code' => 'ROLE_CONTROLLER', 'description' => 'Vérification des TripSeats à l\'embarquement']
                ]
            ]
        ];



        return $this->render('admin/agency/agent/index.html.twig', [
            'page' => 'agent',
            'users' => $users
        ]);
    } //index

    #[Route('/admin/agency/agent/add', name: 'admin_agency_agent_add')]
    #[Route('/admin/agency/agent/{code}/edit', name: 'admin_agency_agent_edit')]
    public function add_and_edit(Request $request, ?string $code = null): Response
    {

        $isEdit = $code !== null;
        $agent = null;

        // Simulation de ta liste de succursales pour l'affectation guichet
        $branches = [
            ['code' => 'SUC-KIN-01', 'name' => 'Victoire - Rond Point'],
            ['code' => 'SUC-MAT-02', 'name' => 'Matadi Ville - Port']
        ];

        // Simulation des rôles disponibles au sein du scope Agency [MCD 4]
        $availableRoles = [
            ['code' => 'ROLE_CASHIER', 'name' => 'Caissier / Guichetier', 'description' => 'Vente de billets'],
            ['code' => 'ROLE_CONTROLLER', 'name' => 'Contrôleur de bord', 'description' => 'Check-in passagers'],
            ['code' => 'ROLE_ACCOUNTANT', 'name' => 'Comptable d\'agence', 'description' => 'Audit financier']
        ];

        if ($isEdit) {
            // Extraction fictive de ton utilisateur [MCD 3 & 5] pour pré-remplir le formulaire
            $agent = [
                'firstname' => 'Antoinette',
                'lastname' => 'Mputu',
                'code' => $code,
                'email' => 'a.mputu@mydigitrans.com',
                'phone' => '+243 897 112 233',
                'userType' => 'agency',
                'branchCode' => 'SUC-KIN-01',
                'currentRoleCode' => 'ROLE_CASHIER',
                'isActive' => true
            ];
        }

        if ($request->isMethod('POST')) {
            $firstname = $request->request->get('firstname');
            $lastname = $request->request->get('lastname');

            $this->addFlash(
                'success',
                $isEdit
                    ? sprintf('Les habilitations de l\'agent %s %s ont été mises à jour.', $firstname, $lastname)
                    : sprintf('Le compte de l\'agent %s %s a été créé et déployé.', $firstname, $lastname)
            );

            return $this->redirectToRoute('admin_agency_user_index');
        }




        return $this->render('admin/agency/agent/form.html.twig', [
            'page' => 'agent',
            'isEdit' => $isEdit,
            'branches' => $branches,
            'available_roles' => $availableRoles,
            'agent' => $agent
        ]);
    } //add_and_edit



    #[Route('/admin/agency/agent/{code}/access/control', name: 'admin_agency_agent_permission')]
    public function permission(string $code, Request $request): Response
    {

        // 1. Profil de l'agent ciblé (Mukendi Jean)
        $agent = [
            'firstname' => 'Jean',
            'lastname' => 'Mukendi',
            'email' => 'j.mukendi@mydigitrans.com',
            'code' => $code,
            'roleLabel' => 'Guichetier Principal'
        ];

        // 2. Matrice complète des segments métiers [MCD 6 & 7] avec leurs valeurs par défaut
        $modules = [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'fa-gauge',
                'value' => 'read',
                'description' => 'Vue d\'ensemble des statistiques de vente de la journée et graphiques d\'évolution.'
            ],
            [
                'key' => 'branches',
                'label' => 'Branch (Succursales)',
                'icon' => 'fa-folder-tree',
                'value' => 'none',
                'description' => 'Consulter ou modifier l\'arborescence, les fiches et les états des autres succursales.'
            ],
            [
                'key' => 'agents',
                'label' => 'Agents (Utilisateurs)',
                'icon' => 'fa-users',
                'value' => 'none',
                'description' => 'Gestion des comptes du personnel, des affectations et consultation de leurs fiches.'
            ],
            [
                'key' => 'bus',
                'label' => 'Bus',
                'icon' => 'fa-bus-simple',
                'value' => 'read',
                'description' => 'Gestion de la flotte automobile, fiches techniques des véhicules et assignations.'
            ],
            [
                'key' => 'routes',
                'label' => 'Trajets (Lignes de transport)',
                'icon' => 'fa-route',
                'value' => 'read_write',
                'description' => 'Configuration des lignes, des arrêts intermédiaires et de la planification horaire.'
            ],
            [
                'key' => 'reservations',
                'label' => 'Réservations',
                'icon' => 'fa-file-circle-check',
                'value' => 'read',
                'description' => 'Consultation des listes d\'attente, enregistrement et validation des billets passagers.'
            ],
            [
                'key' => 'cargo',
                'label' => 'Colis (Fret / Messagerie)',
                'icon' => 'fa-box-open',
                'value' => 'write',
                'description' => 'Enregistrement des colis au départ, suivi de livraison et gestion des réceptions.'
            ]
        ];

        if ($request->isMethod('POST')) {
            // Récupération des choix radios de l'administrateur
            $submittedPerms = $request->request->all('perms');

            $this->addFlash('success', sprintf('Les privilèges d\'accès de %s %s ont été reconfigurés.', $agent['firstname'], $agent['lastname']));
            return $this->redirectToRoute('admin_agency_user_index');
        }




        return $this->render('admin/agency/agent/permission.html.twig', [
            'page' => 'agent',
            'action' => 'edition',
            'modules' => $modules,
            'agent' => $agent,
        ]);
    } //permission

}
