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
                'role' => [
                    'code' => 'ROLE_AGENCY_ADMIN',
                    'description' => 'Gestion totale de l\'agence',
                    'isActive' => true
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
                'role' => [
                    'code' => 'ROLE_CASHIER',
                    'description' => 'Vente de billets et encaissement colis',
                    'isActive' => false
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
                'role' => [
                    'code' => 'ROLE_CONTROLLER',
                    'description' => 'Vérification des TripSeats à l\'embarquement',
                    'isActive' => true
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

            return $this->redirectToRoute('admin_agency_agent_index');
        }




        return $this->render('admin/agency/agent/form.html.twig', [
            'page' => 'agent',
            'isEdit' => $isEdit,
            'branches' => $branches,
            'available_roles' => $availableRoles,
            'agent' => $agent
        ]);
    } //add_and_edit


    #[Route('/admin/agency/agent/{code}/details', name: 'admin_agency_agent_show')]
    public function show(string $code): Response
    {
        // Extraction des tables 3 et 5 de ton MCD
        $agent = [
            'firstname' => 'Jean',
            'lastname' => 'Mukendi',
            'email' => 'j.mukendi@mydigitrans.com',
            'code' => $code,
            'phone' => '+243 897 000 000', // Nettoyage du format
            'isOnline' => true,
            'role' => [
                'name' => 'Caissier Principal',
                'code' => 'ROLE_CASHIER',
                'description' => 'Vente de billets et encaissement colis',
                'isActive' => true
            ],
            'career_history' => [
                // Jointure temporelle issue de la table pivot  
                [
                    // CORRECTION : L'espace après roleName a été supprimé ici
                    'roleName' => 'Caissier principal',
                    'branchName' => 'Gare Centrale - Gombe',
                    'assignedAt' => new \DateTime('2020-10-09'),
                    'assignedBy' => 'Daniel Lukonu',
                    'revokedAt' => new \DateTime('2026-10-09'),
                    'revokedBy' => 'Daniel Lukonu'
                ],
                [
                    'roleName' => 'Guichetier Principal',
                    'branchName' => 'Victoire - Rond Point',
                    'assignedAt' => new \DateTime('2026-10-09'),
                    'assignedBy' => 'Daniel Lukonu',
                    'revokedAt' => null, // En poste actuellement
                    'revokedBy' => null
                ]
            ]
        ];

        return $this->render('admin/agency/agent/show.html.twig', [
            'page' => 'agent',
            'agent' => $agent
        ]);
    } //show
}
