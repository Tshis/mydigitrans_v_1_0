<?php

namespace App\Controller\web\admin\security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    } //reset

    #[Route('/admin/password/change', name: 'admin_password_change')]
    public function change(Request $request): Response
    {

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validation de conformité des jetons de saisie
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('danger', 'La confirmation du nouveau mot de passe ne correspond pas.');
                return $this->redirectToRoute('admin_agency_account_password');
            }

            // Ici, ton UserPasswordHasherInterface de Symfony encodera et hydratera l'entité User [MCD 3]

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            return $this->redirectToRoute('admin_agency_account_password');
        }




        return $this->render('admin/password/change.html.twig', [
            'page' => 'setting',
        ]);
    } //change



}
