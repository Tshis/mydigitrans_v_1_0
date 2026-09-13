<?php

namespace App\Controller\web\admin\agency\Report;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController extends   AbstractController
{


    #[Route('/admin/agency/report/reports', name: 'admin_agency_report_index')]
    public function index(Request $request): Response
    {
        return $this->render('admin/agency/report/index.html.twig', [
            'page' => 'report',
        ]);
    } //index

}
