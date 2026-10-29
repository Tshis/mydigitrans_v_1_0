<?php

declare(strict_types=1);

namespace App\Dto\public\agency;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription d'une agence : la compagnie, son siège (adresse principale) et son responsable général.
 */
final class AgencyRegistrationData
{
    // ------------------------------------------------------------ agence
    #[Assert\NotBlank(message: 'Indiquez le nom commercial de la compagnie.')]
    #[Assert\Length(min: 2, max: 150, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    public ?string $agencyName = null;

    #[Assert\Length(max: 100, maxMessage: 'Le numéro RCCM est trop long.')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9\/ .:_-]*$/', message: 'Le numéro RCCM contient des caractères non autorisés.')]
    public ?string $registrationNumber = null;

    #[Assert\Length(max: 100, maxMessage: 'Le numéro NIF est trop long.')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9\/ .:_-]*$/', message: 'Le numéro NIF contient des caractères non autorisés.')]
    public ?string $taxNumber = null;

    #[Assert\NotBlank(message: 'Indiquez le téléphone de l\'agence.')]
    #[Assert\Regex(pattern: '/^\+?[0-9][0-9 ().-]{7,19}$/', message: 'Numéro de téléphone invalide.')]
    public ?string $agencyPhone = null;

    #[Assert\NotBlank(message: 'Indiquez l\'email de l\'agence.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse email est trop longue.')]
    public ?string $agencyEmail = null;

    // ------------------------------------------------------------ siège
    #[Assert\NotBlank(message: 'Indiquez la ville principale.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de la ville est trop long.')]
    public ?string $mainCity = null;

    #[Assert\NotBlank(message: 'Indiquez le pays.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom du pays est trop long.')]
    public ?string $country = null;

    #[Assert\NotBlank(message: 'Indiquez l\'adresse du siège.')]
    #[Assert\Length(max: 255, maxMessage: 'L\'adresse est trop longue.')]
    public ?string $address = null;

    // ------------------------------------------------------------ affiliation
    /** Facultatif : jeton de parrainage du partenaire qui a recruté l'agence. */
    #[Assert\Length(max: 64, maxMessage: 'Code de parrainage invalide.')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9]*$/', message: 'Code de parrainage invalide.')]
    public ?string $sponsorToken = null;

    // ------------------------------------------------------------ responsable
    #[Assert\NotBlank(message: 'Indiquez le nom du responsable.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    #[Assert\Regex(pattern: '/^[\p{L}][\p{L}\s\'’-]*$/u', message: 'Le nom contient des caractères non autorisés.')]
    public ?string $ownerLastname = null;

    #[Assert\NotBlank(message: 'Indiquez le prénom du responsable.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom est trop court.', maxMessage: 'Le prénom est trop long.')]
    #[Assert\Regex(pattern: '/^[\p{L}][\p{L}\s\'’-]*$/u', message: 'Le prénom contient des caractères non autorisés.')]
    public ?string $ownerFirstname = null;

    #[Assert\NotBlank(message: 'Indiquez le téléphone du responsable.')]
    #[Assert\Regex(pattern: '/^\+?[0-9][0-9 ().-]{7,19}$/', message: 'Numéro de téléphone invalide.')]
    public ?string $ownerPhone = null;

    #[Assert\NotBlank(message: 'Indiquez l\'email du responsable.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse email est trop longue.')]
    public ?string $ownerEmail = null;

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
