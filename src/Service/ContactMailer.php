<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\home\ContactData;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Envoie à l'équipe MyDigitrans le message écrit dans le formulaire de contact
 * et transmet un accusé de réception automatique au visiteur.
 */
final class ContactMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'CONTACT_RECIPIENT')]
        private readonly string $recipient,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {}


    /**
     * @throws TransportExceptionInterface
     */
    public function send(ContactData $data, ?string $ip, \DateTimeImmutable $now): void
    {
        $name = trim((string) preg_replace('/[\r\n\t]+/', ' ', (string) $data->fullName));
        $subject = $data->subject?->label() ?? 'Autre';


        // 1. E-mail destiné à l'équipe MyDigitrans
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'MyDigitrans - Formulaire de contact'))
            ->to($this->recipient)
            ->replyTo(new Address((string) $data->email, $name))
            ->subject(sprintf('[Contact] %s - %s', $subject, $name))
            ->htmlTemplate('emails/contact_message.html.twig')
            ->textTemplate('emails/contact_message.txt.twig')
            ->context([
                'full_name' => $name,
                'sender_email' => $data->email,
                'subject_label' => $subject,
                'message' => $data->message,
                'ip' => $ip,
                'sent_at' => $now,
            ]);

        $this->mailer->send($email);

        // 2. Accusé de réception destiné au visiteur (Instanciation propre et dédiée)
        $acknowledgement = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'MyDigitrans'))
            ->to(new Address((string) $data->email, $name))
            ->subject('MyDigitrans - Accusé de réception de votre message')
            ->htmlTemplate('emails/contact_acknowledgement.html.twig')
            ->textTemplate('emails/contact_acknowledgement.txt.twig')
            ->context([
                'full_name' => $name,
                'subject_label' => $subject,
                'message' => $data->message,
                'support_email' => $this->fromAddress,
            ]);

        $this->mailer->send($acknowledgement);
    }
}
