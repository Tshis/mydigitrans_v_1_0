<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{

    #[Route('/admin/agency/dashboard', name: 'admin_agency_dashboard')]
    public function index()
    {
        $user = "super";
        $route = "";

        if ($user == "super") {
            return $this->redirectToRoute('admin_agency_main_dashboard');
        } else {
            return $this->redirectToRoute('admin_agency_branch_dashboard');
        }
    } //index

    #[Route('/admin/agency/main/dashboard', name: 'admin_agency_main_dashboard')]
    public function main(ChartBuilderInterface $chartBuilder): Response
    {


        // --- 📊 GRAPH 1 : REVENUS (Barres - 7 derniers jours) ---
        $revenueChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $revenueChart->setData([
            'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            'datasets' => [
                [
                    'label' => 'Ventes Globale (FC)',
                    'backgroundColor' => '#2563eb',
                    'borderRadius' => 5,
                    'data' => [1200000, 1900000, 1500000, 2500000, 2200000, 3000000, 2800000],
                ],
            ],
        ]);
        $revenueChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
        ]);

        // --- 🥧 GRAPH 2 : RÉPARTITION SUCCURSALES (Doughnut) ---
        $branchChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $branchChart->setData([
            'labels' => ['Kinshasa', 'Matadi', 'Boma', 'Muanda'],
            'datasets' => [
                [
                    'backgroundColor' => ['#2563eb', '#10b981', '#f59e0b', '#6366f1'],
                    'data' => [45, 30, 15, 10],
                ],
            ],
        ]);
        $branchChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '70%',
            'plugins' => ['legend' => ['position' => 'bottom']],
        ]);

        // --- 📋 RENDU VUE AVEC LES KPI STATIQUES ---
        return $this->render('admin/agency/dashboard/main.html.twig', [
            'page' => 'dashboard',
            'revenueChart' => $revenueChart,
            'branchChart' => $branchChart,
            // Données du bandeau KPI
            'kpi' => [
                'ca_global' => '15.100.000',
                'ca_global_usd' => '6.000',
                'croissance' => '+12.4',
                'championne_nom' => 'Kinshasa',
                'championne_ca' => '6.795.000',
                'championne_ca_usd' => '2.800',
                'transactions' => '1.420',
            ],
        ]);
    } //main

    #[Route('/admin/agency/branch/dashboard', name: 'admin_agency_branch_dashboard')]
    public function branch(ChartBuilderInterface $chartBuilder): Response
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            'datasets' => [
                [
                    'label' => 'Recettes Hebdo (FC)',
                    'borderColor' => '#1473bc', // Ta couleur $success
                    'backgroundColor' => 'rgb(52, 119, 207)',
                    'data' => [1800000, 2200000, 2800000, 1900000, 2400000, 3100000, 1500000],
                    'tension' => 0.4
                ]
            ]
        ]);

        return $this->render('admin/agency/dashboard/branch.html.twig', [
            'page' => 'dashboard',
            'chart' => $chart
        ]);
    } //branch

}
