<?php

namespace App\Controller\web\public;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    #[Route('/password/forgot', name: 'security_password_forgot', methods: ['GET', 'POST'])]
    public function forgot(
        Request $request,
        UserRepository $userRepository,
        PasswordResetMailer $mailer
    ): Response {
        $error = null;
        $success = false;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                // Extraction des rôles depuis la table UserRole du MCD
                $userRoles = [];
                foreach ($user->getUserRoles() as $userRole) {
                    $userRoles[] = $userRole->getRole()->getCode();
                }

                // Restriction exclusive : PARTNER, SUPER_ADMIN et AGENCY_ADMIN
                $allowedRoles = ['ROLE_SUPER_ADMIN', 'ROLE_AGENCY_ADMIN', 'ROLE_PARTNER'];
                $hasAccess = array_intersect($allowedRoles, $userRoles);

                if (!empty($hasAccess)) {
                    try {
                        // Génération du nouveau code unique grâce à votre méthode
                        $newCode = $this->uniqueUserCode();

                        // On met à jour le code de l'utilisateur (il servira de jeton temporaire)
                        $user->setCode($newCode);
                        $this->em->flush();

                        // Envoi de l'e-mail avec le lien contenant ce code unique
                        $mailer->sendResetLink($user, $newCode);
                        $success = true;
                    } catch (\Exception $e) {
                        $error = "Une erreur est survenue lors de la sécurisation de votre demande.";
                    }
                } else {
                    $error = "Veuillez contacter votre administateur, cette interface est reservée";
                }
            } else {
                $error = "Aucun compte associé à cette adresse e-mail.";
            }
        }

        return $this->render('public/security/password/forgot.html.twig', [
            'page' => 'login',
            'error' => $error,
            'success' => $success
        ]);
    }

    #[Route('/password/reset/{code}', name: 'security_password_reset', methods: ['GET', 'POST'])]
    public function reset(
        string $code,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // On cherche l'utilisateur qui possède actuellement ce code unique
        $user = $userRepository->findOneBy(['code' => $code]);

        if (!$user) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a déjà été utilisé.');
            return $this->redirectToRoute('security_password_forgot');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirm = $request->request->get('password_confirm');

            if ($password === $confirm) {
                // Hachage du nouveau mot de passe sécurisé
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                // Sécurité maximale : On regénère un nouveau code unique immédiatement !
                // L'ancien lien extrait de l'e-mail devient instantanément inutilisable.
                $user->setCode($this->uniqueUserCode());

                $this->em->flush();

                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
                return $this->redirectToRoute('security_login_index');
            }

            $this->addFlash('error', 'Les mots de passe saisis ne correspondent pas.');
        }

        return $this->render('public/security/password/reset.html.twig', [
            'page' => 'login',
            'code' => $code
        ]);
    }

    /**
     * Génère un code utilisateur unique au format USR-XXXXXXXX
     */
    private function uniqueUserCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $repository = $this->em->getRepository(User::class);

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $code = 'USR-';
            for ($i = 0; $i < 8; ++$i) {
                $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }

            if (null === $repository->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        // Remplacement de l'exception spécifique par une exception standard pour le contrôleur
        throw new \RuntimeException('Impossible de générer un code utilisateur unique après 10 tentatives.');
    }
}
