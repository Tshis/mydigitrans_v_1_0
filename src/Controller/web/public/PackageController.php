<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackageController extends AbstractController
{
    #[Route('/public/package', name: 'public_package_index')]
    public function index(): Response
    {
        return $this->render('public/package/index.html.twig', [
            'page' => 'package',
        ]);
    } //index


    #[Route('/public/package/results', name: 'public_package_results')]
    public function results(): Response
    {
        return $this->render('public/package/results.html.twig', [
            'page' => 'package',
        ]);
    } //results



}
