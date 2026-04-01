<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'security_login_index')]
    public function index(): Response
    {
        return $this->render('public/security/login/index.html.twig', [
            'page' => 'login',
            'error' => []
        ]);
    } //index
}
