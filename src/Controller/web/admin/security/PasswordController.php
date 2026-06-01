<?php

namespace App\Controller\web\admin\security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PasswordController extends AbstractController
{

    #[Route('/admin/password/{code}/reset', name: 'admin_password_reset')]
    public function reset(): Response
    {
        return $this->render('admin/password/reset.html.twig', [
            'page' => 'agent',
        ]);
    } //edit

}
