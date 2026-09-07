<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/admin/agency/agent/new', name: 'admin_agency_agent_add')]
    public function add(): Response
    {
        return $this->render('admin/agency/agent/form.html.twig', [
            'page' => 'agent',
            'action' => 'ajout'
        ]);
    } //add

    #[Route('/admin/agency/agent/{code}/modify', name: 'admin_agency_agent_edit')]
    public function edit(): Response
    {
        return $this->render('admin/agency/agent/form.html.twig', [
            'page' => 'agent',
            'action' => 'edition'
        ]);
    } //edit

    #[Route('/admin/agency/agent/{code}/access/control', name: 'admin_agency_agent_permission')]
    public function permission(): Response
    {
        return $this->render('admin/agency/agent/permission.html.twig', [
            'page' => 'agent',
            'action' => 'edition'
        ]);
    } //permission



}
