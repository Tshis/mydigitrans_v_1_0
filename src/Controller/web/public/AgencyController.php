<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AgencyController extends AbstractController
{

    #[Route('/public/agency/create', name: 'public_agency_add')]
    public function add(): Response
    {
        return $this->render('public/agency/add.html.twig', [
            'page' => 'agency',
        ]);
    } //index

}
