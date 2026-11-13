<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Escale intermédiaire d'une ligne de transport (RouteStop).
 */
final class RouteStopData
{
    #[Assert\NotBlank(message: 'Indiquez la ville d\'escale.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Nom de ville trop court.', maxMessage: 'Nom de ville trop long.')]
    public ?string $city = null;

    #[Assert\NotBlank(message: 'Indiquez le pays.')]
    #[Assert\Length(min: 2, max: 100)]
    public ?string $country = 'RDC';

    #[Assert\NotNull(message: 'Indiquez la distance depuis le départ.')]
    #[Assert\Positive(message: 'La distance doit être supérieure à 0 km.')]
    public ?float $distance = null;

    #[Assert\NotBlank(message: 'Indiquez la durée estimée depuis le départ (ex: 2h15 ou 135).')]
    #[Assert\Regex(
        pattern: '/^(\d+h\d{1,2}|\d+)$/i',
        message: 'Format invalide. Utilisez par exemple 2h15 ou un nombre de minutes (ex: 135).'
    )]
    public ?string $duration = null;
}
