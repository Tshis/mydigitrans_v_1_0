<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création ou modification d'une succursale (point de vente) d'une agence.
 */
final class BranchData
{
    #[Assert\NotBlank(message: 'Indiquez le nom de la succursale.')]
    #[Assert\Length(min: 2, max: 150, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    public ?string $name = null;

    #[Assert\Length(max: 50, maxMessage: 'Le code succursale est trop long (50 caractères maximum).')]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9_-]*$/',
        message: 'Le code ne peut contenir que des lettres, chiffres, tirets ou underscores.'
    )]
    public ?string $code = null;

    #[Assert\NotBlank(message: 'Indiquez la ville de la succursale.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Nom de ville trop court.', maxMessage: 'Nom de ville trop long.')]
    public ?string $city = null;

    #[Assert\NotBlank(message: 'Choisissez le pays de la succursale.')]
    #[Assert\Country(message: 'Le pays sélectionné est invalide.')]
    public ?string $country = 'CD';

    /** Identifiant du gérant / responsable affecté à cette succursale (optionnel). */
    public ?int $managerId = null;
}
