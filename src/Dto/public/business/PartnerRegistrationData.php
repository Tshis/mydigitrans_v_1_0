<?php

declare(strict_types=1);

namespace App\Dto\public\business;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription d'un partenaire (apporteur d'affaires) depuis la page publique.
 */
final class PartnerRegistrationData
{
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    #[Assert\Regex(pattern: '/^[\p{L}][\p{L}\s\'’-]*$/u', message: 'Le nom contient des caractères non autorisés.')]
    public ?string $lastname = null;

    #[Assert\NotBlank(message: 'Indiquez votre prénom.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom est trop court.', maxMessage: 'Le prénom est trop long.')]
    #[Assert\Regex(pattern: '/^[\p{L}][\p{L}\s\'’-]*$/u', message: 'Le prénom contient des caractères non autorisés.')]
    public ?string $firstname = null;

    #[Assert\NotBlank(message: 'Indiquez votre numéro de téléphone.')]
    #[Assert\Regex(pattern: '/^\+?[0-9][0-9 ().-]{7,19}$/', message: 'Numéro de téléphone invalide.')]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'Indiquez votre adresse email.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse email est trop longue.')]
    public ?string $email = null;

    /** Facultatif : jeton de parrainage (referral_token) du partenaire qui vous a recruté. */
    #[Assert\Length(max: 64, maxMessage: 'Code de parrainage invalide.')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9]*$/', message: 'Code de parrainage invalide.')]
    public ?string $sponsorToken = null;

    #[Assert\NotBlank(message: 'Choisissez un mot de passe.')]
    #[Assert\Length(min: 8, max: 128, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le mot de passe est trop long.')]
    #[Assert\Regex(pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/', message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.')]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'Confirmez votre mot de passe.')]
    #[Assert\IdenticalTo(propertyPath: 'password', message: 'Les mots de passe ne correspondent pas.')]
    public ?string $confirmPassword = null;

    #[Assert\IsTrue(message: 'Vous devez accepter les conditions pour continuer.')]
    public bool $acceptLegal = false;
}
