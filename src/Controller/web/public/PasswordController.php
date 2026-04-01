<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PasswordController extends AbstractController
{
    #[Route('/password/forgot', name: 'security_password_forgot')]
    public function forgot(): Response
    {
        return $this->render('public/security/password/forgot.html.twig', [
            'page' => 'login',
            'error' => []
        ]);
    } //forgot

    #[Route('/password/reset', name: 'security_password_reset')]
    public function reset(): Response
    {
        return $this->render('public/security/password/reset.html.twig', [
            'page' => 'login',
            'error' => []
        ]);
    } //reset
}
