<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BranchController extends AbstractController
{

    #[Route('/admin/agency/branches', name: 'admin_agency_branch_index')]
    public function index(): Response
    {
        return $this->render('admin/agency/branch/index.html.twig', [
            'page' => 'branch',
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


}
