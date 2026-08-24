<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TicketController extends AbstractController
{

    #[Route('/admin/agency/tickets', name: 'admin_agency_ticket_index')]
    public function index(Request $request): Response
    {
        $tickets = [
            [
                'reference' => 'TK-88942',
                'passengerName' => 'Mputu Antoinette',
                'passengerPhone' => '+243 897 112 233',
                'tripCode' => 'KIN-KKW-240826',
                'seatNumber' => 14,
                'status' => 'valid',
                'createdAt' => new \DateTime('-2 days')
            ],
            [
                'reference' => 'TK-88942',
                'passengerName' => 'Mputu Antoinette',
                'passengerPhone' => '+243 897 112 233',
                'tripCode' => 'KIN-KKW-240826',
                'seatNumber' => 14,
                'status' => 'expired',
                'createdAt' => new \DateTime('-2 days')
            ],
            [
                'reference' => 'TK-88942',
                'passengerName' => 'Mputu Antoinette',
                'passengerPhone' => '+243 897 112 233',
                'tripCode' => 'KIN-KKW-240826',
                'seatNumber' => 14,
                'status' => 'used',
                'createdAt' => new \DateTime('-2 days')
            ],
            // ...
        ];


        return $this->render('admin/agency/ticket/index.html.twig', [
            'page' => 'ticket',
            'tickets' => $tickets
        ]);
    } //index

    #[Route('/admin/agency/ticket/scan', name: 'admin_agency_ticket_scan')]
    public function scan(Request $request): Response
    {

        return $this->render('admin/agency/ticket/scan.html.twig', [
            'page' => 'ticket',
        ]);
    } //scan

}
