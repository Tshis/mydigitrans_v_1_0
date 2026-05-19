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
        return $this->render('admin/agency/dashboard-main.html.twig', [
            'page' => 'Branch',
        ]);
    } //index


}
