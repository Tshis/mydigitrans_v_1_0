<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BusinessController extends AbstractController
{

    #[Route('/business', name: 'business_index')]
    public function index(): Response
    {
        return $this->render('public/business/index.html.twig', [
            'page' => 'business',
        ]);
    } //index

    #[Route('/business/parter-onboarding', name: 'business_onboarding')]
    public function onboarding(): Response
    {
        return $this->render('public/business/onboarding.html.twig', [
            'page' => 'business',
        ]);
    } //onboarding

}
