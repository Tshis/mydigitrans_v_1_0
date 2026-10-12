<?php
// src/Controller/Public/PublicLegalController.php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/conditions-generales-utilisation', name: 'public_legal_cgu', methods: ['GET'])]
    public function cgu(): Response
    {
        return $this->render('public/legal/cgu.html.twig', [
            'page' => 'cgu'
        ]);
    } //cgu

    #[Route('/politique-confidentialite', name: 'public_legal_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('public/legal/privacy.html.twig', [
            'page' => 'privacy'
        ]);
    } //privacy
}
