<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CashierController extends AbstractController
{

    #[Route('/admin/agency/cash-register', name: 'admin_agency_cashier_index')]
    public function index(Request $request): Response
    {
        return $this->render('admin/agency/cashier/index.html.twig', [
            'page' => 'cashier',
        ]);
    } //index
}
