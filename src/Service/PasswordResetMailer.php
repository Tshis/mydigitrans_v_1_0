<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {}

    public function sendResetLink(User $user, string $code): void
    {
        // Génération de l'URL absolue vers la route de réinitialisation
        $url = $this->router->generate('security_password_reset', ['code' => $code], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'MyDigitrans Support'))
            ->to(new Address((string) $user->getEmail(), sprintf('%s %s', $user->getFirstname(), $user->getLastname())))
            ->subject('MyDigitrans - Réinitialisation de votre mot de passe')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context([
                'user' => $user,
                'reset_url' => $url,
            ]);

        $this->mailer->send($email);
    }
}
