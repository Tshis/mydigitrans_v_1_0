<?php

namespace App\Controller\web\public;

use App\Entity\User;
use App\Enum\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'security_login_index')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        /**
         * @var User
         */
        $user = $this->getUser();

        if ($user !== null) {
            // À adapter : remplace ces noms de routes par tes vrais tableaux de bord.
            $route = match ($user instanceof User ? $user->getUserType() : null) {
                UserType::Platform => 'admin_platform_dashboard',
                UserType::Agency => 'admin_agency_dashboard',
                UserType::Partner => 'admin_partner_dashboard_index',
                default => 'security_login_index',
            };

            return $this->redirectToRoute($route);
        }


        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('public/security/login/index.html.twig', [
            'page' => 'login',
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    } //index


    #[Route('/logout', name: 'security_login_logout')]
    public function logout(): Response
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');

        return $this->render('public/security/login/index.html.twig', [
            'page' => 'login',
            'error' => []
        ]);
    } //logout

}
