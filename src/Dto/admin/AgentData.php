<?php

declare(strict_types=1);

namespace App\Dto\admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création ou modification d'un utilisateur (agent d'agence ou membre de la plateforme).
 * Le mot de passe initial n'est exigé qu'à la création (groupe « create »).
 */
final class AgentData
{
    #[Assert\NotBlank(message: 'Indiquez le prénom.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom est trop court.', maxMessage: 'Le prénom est trop long.')]
    #[Assert\Regex(pattern: '/^[\p{L}][\p{L}\s\'’-]*$/u', message: 'Le prénom contient des caractères non autorisés.')]
    public ?string $firstname = null;

    #[Assert\NotBlank(message: 'Indiquez le nom.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    #[Assert\Regex(pattern: '/^[\p{L}][\p{L}\s\'’-]*$/u', message: 'Le nom contient des caractères non autorisés.')]
    public ?string $lastname = null;

    #[Assert\NotBlank(message: 'Indiquez l\'adresse email.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse email est trop longue.')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Indiquez le numéro de téléphone.')]
    #[Assert\Regex(pattern: '/^\+?[0-9][0-9 ().-]{7,19}$/', message: 'Numéro de téléphone invalide.')]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'Choisissez un mot de passe initial.', groups: ['create'])]
    #[Assert\Length(min: 8, max: 128, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le mot de passe est trop long.', groups: ['create'])]
    #[Assert\Regex(pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/', message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.', groups: ['create'])]
    public ?string $password = null;

    /** Succursale d'affectation (agences uniquement) ; vide = siège / accès général. */
    public ?string $branchCode = null;

    /** Code du rôle système (Role::code) ; la liste est limitée aux rôles de la portée. */
    #[Assert\NotBlank(message: 'Choisissez une fonction.')]
    public ?string $roleCode = null;

    /** « 1 » = compte actif, « 0 » = compte bloqué. */
    public string $isActive = '0';
}
