<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FareController extends AbstractController
{

    #[Route('/admin/agency/fare/add', name: 'admin_agency_fare_add')]
    #[Route('/admin/agency/line/{slug}/fare/add', name: 'admin_agency_line_fare_add')]
    public function add(Request $request, $slug = null): Response
    {


        return $this->render('admin/agency/fare/add.html.twig', [
            'page' => 'fare',
            'slug' => $slug
        ]);
    } //add

}
