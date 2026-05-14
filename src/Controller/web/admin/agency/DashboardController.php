<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{

    #[Route('/admin/agency/dashboard2', name: 'app_dashboard')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'labels' => ['Janvier', 'Février', 'Mars', 'Avril'],
            'datasets' => [
                [
                    'label' => 'Ventes SF8',
                    'backgroundColor' => 'rgb(255, 99, 132)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => [10, 25, 18, 40],
                ],
            ],
        ]);

        return $this->render('admin/agency/dashboard2.html.twig', [
            'page' => 'dashboard',
            'my_chart' => $chart,
        ]);
    } //index

    #[Route('/admin/agency/dashboard', name: 'admin_agency_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/agency/dashboard.html.twig', [
            'page' => 'dashboard',
        ]);
    } //dashboard

}
