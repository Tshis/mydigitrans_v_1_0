<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BranchController extends AbstractController
{

    #[Route('/admin/agency/branches', name: 'admin_agency_branch_index')]
    public function index(): Response
    {

        // Simulation des points de vente régionaux de l'agence [MCD 15]
        $branches = [
            [
                'code' => 'SUC-KIN-01',
                'name' => 'Victoire - Rond Point',
                'city' => 'Kinshasa',
                'address' => 'Croisement de l\'Avenue Victoire et Kasa-Vubu',
                'managerName' => 'Alphonse Kabeya',
                'status' => 'active',
                'statusLabel' => 'Active'
            ],
            [
                'code' => 'SUC-MAT-02',
                'name' => 'Matadi Ville - Port',
                'city' => 'Kongo-Central',
                'address' => 'Avenue de la Reine, face au Port',
                'managerName' => 'Thérèse Mbuyi',
                'status' => 'active',
                'statusLabel' => 'Active'
            ],
            [
                'code' => 'SUC-KKW-03',
                'name' => 'Kikwit Centre - RN1',
                'city' => 'Kwilu',
                'address' => 'Avenue Lumumba, Arrêt Grand Marché',
                'managerName' => 'Sylvain Mukendi',
                'status' => 'inactive',
                'statusLabel' => 'Suspendue'
            ]
        ];

        return $this->render('admin/agency/branch/index.html.twig', [
            'page' => 'branch',
            'branches' => $branches
        ]);
    } //index

    #[Route('/admin/agency/branch/new', name: 'admin_agency_branch_add')]
    public function add(): Response
    {
        // 1. Création du formulaire à la volée (sans entité)
        $form = $this->createFormBuilder()
            ->add('pays', CountryType::class, [
                'placeholder' => 'Rechercher un pays...',
                'autocomplete' => true, // Option Symfony UX
            ])
            ->getForm();

        return $this->render('admin/agency/branch/add.html.twig', [
            'page' => 'branch',
            'form' => $form->createView(),
        ]);
    } //add

    #[Route('/admin/agency/branch/modification/{code}', name: 'admin_agency_branch_edit')]
    public function edit(): Response
    {
        // 1. Création du formulaire à la volée (sans entité)
        $form = $this->createFormBuilder()
            ->add('pays', CountryType::class, [
                'placeholder' => 'Rechercher un pays...',
                'autocomplete' => true, // Option Symfony UX
            ])
            ->getForm();

        return $this->render('admin/agency/branch/edit.html.twig', [
            'page' => 'branch',
            'form' => $form->createView(),
        ]);
    } //edit


    // src/Controller/Admin/Agency/BranchController.php

    #[Route('/admin/agency/branch/{code}/details', name: 'admin_agency_branch_show')]
    public function show(string $code, Request $request): Response
    {
        // Simulation d'une extraction de succursale en BDD selon ton MCD 15
        $branch = [
            'code' => $code,
            'name' => 'Victoire - Rond Point',
            'city' => 'Kinshasa',
            'address' => 'Croisement de l\'Avenue Victoire et Kasa-Vubu, Zone Commerciale',
            'phone' => '+243 812 345 678',
            'status' => 'active',
            'statusLabel' => 'Active',

            // Simulation du personnel lié à cette succursale
            'staff' => [
                [
                    'name' => 'Kalonji Beya',
                    'roleLabel' => 'Chef de Gare / Superviseur',
                    'email' => 'k.beya@mydigitrans.cd',
                    'phone' => '+243 822 445 112',
                    'isOnline' => true
                ],
                [
                    'name' => 'Antoinette Mputu',
                    'roleLabel' => 'Guichetière / Caissière',
                    'email' => 'a.mputu@mydigitrans.cd',
                    'phone' => '+243 897 112 233',
                    'isOnline' => false
                ]
            ]
        ];

        if ($request->isMethod('POST')) {
            $newStatus = $request->request->get('branch_status');
            $this->addFlash('success', sprintf('Le statut de la succursale %s a été mis à jour.', $branch['name']));
            return $this->redirectToRoute('admin_agency_branch_index');
        }

        return $this->render('admin/agency/branch/show.html.twig', [
            'page' => 'branch',
            'branch' => $branch
        ]);
    }
}
