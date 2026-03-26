<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LinesController extends AbstractController
{
    #[Route('/public/lines', name: 'public_lines_index')]
    public function index(): Response
    {
        return $this->render('public/lines/index.html.twig', [
            'page' => 'lines',
        ]);
    } //index

    #[Route('/public/lines/results', name: 'public_lines_results')]
    public function results(): Response
    {
        return $this->render('public/lines/results.html.twig', [
            'page' => 'lines',
        ]);
    } //results


}
