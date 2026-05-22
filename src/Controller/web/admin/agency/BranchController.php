<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/admin/agency/branches/new', name: 'admin_agency_branch_add')]
    public function add(): Response
    {
        return $this->render('admin/agency/branch/add.html.twig', [
            'page' => 'branch',
        ]);
    } //add

    #[Route('/admin/agency/branches/modification', name: 'admin_agency_branch_edit')]
    public function edit(): Response
    {
        return $this->render('admin/agency/branch/edit.html.twig', [
            'page' => 'branch',
        ]);
    } //edit


}
