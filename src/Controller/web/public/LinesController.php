<?php

namespace App\Controller\web\public;

use App\Service\BusLayoutGridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/public/lines/{slug}/booking', name: 'public_lines_booking')]
    public function booking($slug, Request $request, BusLayoutGridBuilder $busLayoutGridBuilder): Response
    {
        $session = $request->getSession();

        $layouts = $session->get('bus_layout', []);

        // simulation bus -> layout
        $busToLayoutMap = [
            'kin-matadi-trans-david-01' => 1,
            'kin-matadi-trans-david-02' => 2,
            'kin-matadi-trans-david-03' => 3,
        ];

        $layoutId = $busToLayoutMap[$slug] ?? null;

        $busLayout = $layouts[$layoutId] ?? null;

        if (!$busLayout) {
            throw $this->createNotFoundException('Bus layout introuvable');
        }

        return $this->render('public/lines/booking.html.twig', [
            'page' => 'lines',
            'seatmap' => $busLayoutGridBuilder->build($busLayout),
        ]);
    } //booking


}
