<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création ou modification d'un véhicule de la flotte d'agence.
 * Le code interne et le gabarit (busLayoutId) ne sont exigés qu'à la création (groupe « create »).
 */
final class BusData
{
    #[Assert\Length(max: 50, maxMessage: 'Le code interne est trop long (50 caractères maximum).')]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9_-]*$/',
        message: 'Le code interne ne peut contenir que des lettres, chiffres, tirets ou underscores.'
    )]
    public ?string $code = null;

    #[Assert\Length(max: 100, maxMessage: 'Le nom commercial est trop long (100 caractères maximum).')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Indiquez le numéro de plaque d\'immatriculation.')]
    #[Assert\Length(min: 3, max: 30, minMessage: 'La plaque est trop courte.', maxMessage: 'La plaque est trop longue.')]
    public ?string $plateNumber = null;

    #[Assert\NotBlank(message: 'Indiquez la marque du véhicule.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'La marque est trop courte.', maxMessage: 'La marque est trop longue.')]
    public ?string $brand = null;

    #[Assert\NotBlank(message: 'Indiquez le modèle du véhicule.')]
    #[Assert\Length(min: 1, max: 100, maxMessage: 'Le modèle est trop long.')]
    public ?string $model = null;

    #[Assert\NotNull(message: 'Indiquez l\'année de fabrication.')]
    #[Assert\Range(
        min: 1990,
        max: 2027,
        notInRangeMessage: 'L\'année de fabrication doit être comprise entre {{ min }} et {{ max }}.'
    )]
    public ?int $year = null;

    #[Assert\NotNull(message: 'Indiquez le kilométrage actuel.')]
    #[Assert\PositiveOrZero(message: 'Le kilométrage ne peut pas être négatif.')]
    public ?int $mileage = 0;

    #[Assert\NotNull(message: 'Choisissez un gabarit d\'usine.', groups: ['create'])]
    #[Assert\Positive(message: 'Gabarit invalide.', groups: ['create'])]
    public ?int $busLayoutId = null;
}
