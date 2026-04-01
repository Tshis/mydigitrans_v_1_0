<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{

    #[Route('/admin/agency/dashboard', name: 'admin_agency_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/agency/dashboard.html.twig', [
            'page' => 'dashboard',
        ]);
    } //dashboard

}
