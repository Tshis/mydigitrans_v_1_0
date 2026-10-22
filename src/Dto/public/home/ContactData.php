<?php

declare(strict_types=1);

namespace App\Dto\public\home;

use App\Enum\ContactSubject;
use Symfony\Component\Validator\Constraints as Assert;

final class ContactData
{
    #[Assert\NotBlank(message: 'Indiquez votre nom complet.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    #[Assert\Regex(pattern: '/^[\p{L}][\p{L}\s\'’.-]*$/u', message: 'Le nom contient des caractères non autorisés.')]
    public ?string $fullName = null;

    #[Assert\NotBlank(message: 'Indiquez votre adresse email.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse email est trop longue.')]
    public ?string $email = null;

    #[Assert\NotNull(message: 'Choisissez un objet.')]
    public ?ContactSubject $subject = null;

    #[Assert\NotBlank(message: 'Écrivez votre message.')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Votre message est trop court ({{ limit }} caractères minimum).', maxMessage: 'Votre message est trop long ({{ limit }} caractères maximum).')]
    public ?string $message = null;

    /** Piège à robots : champ invisible pour les visiteurs, qu'un robot remplit. Doit rester vide. */
    public ?string $website = null;
}
