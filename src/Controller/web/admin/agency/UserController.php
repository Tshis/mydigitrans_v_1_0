<?php

namespace App\Controller\web\admin\agency;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{

    #[Route('/admin/agency/agents', name: 'admin_agency_agent_index')]
    public function index(): Response
    {
        return $this->render('admin/agency/agent/index.html.twig', [
            'page' => 'agent',
        ]);
    } //index

}
