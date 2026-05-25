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

    #[Route('/admin/agency/agent/new', name: 'admin_agency_agent_add')]
    public function add(): Response
    {
        return $this->render('admin/agency/agent/add.html.twig', [
            'page' => 'agent',
        ]);
    } //add

}
