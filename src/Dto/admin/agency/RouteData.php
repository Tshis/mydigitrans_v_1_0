<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création d'une ligne de transport et de son tracé d'arrêts (Route + RouteStop).
 */
final class RouteData
{
    #[Assert\NotBlank(message: 'Indiquez le nom commercial de la ligne.')]
    #[Assert\Length(min: 3, max: 150, minMessage: 'Le nom de la ligne est trop court.', maxMessage: 'Le nom de la ligne est trop long.')]
    public ?string $name = null;

    #[Assert\Length(max: 50, maxMessage: 'Le code ligne est trop long (50 caractères maximum).')]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9_-]*$/',
        message: 'Le code ligne ne peut contenir que des lettres, chiffres, tirets ou underscores.'
    )]
    public ?string $code = null;


    #[Assert\NotBlank(message: 'Indiquez la ville de départ.')]
    #[Assert\Length(min: 2, max: 100)]
    public ?string $departureCity = 'Kinshasa';

    #[Assert\NotBlank(message: 'Choisissez le pays de départ.')]
    #[Assert\Country(message: 'Le pays de départ sélectionné est invalide.')]
    public ?string $departureCountry = 'CD';

    #[Assert\NotBlank(message: 'Indiquez la ville terminus.')]
    #[Assert\Length(min: 2, max: 100)]
    public ?string $terminusCity = null;

    #[Assert\NotBlank(message: 'Choisissez le pays terminus.')]
    #[Assert\Country(message: 'Le pays terminus sélectionné est invalide.')]
    public ?string $terminusCountry = 'CD';

    #[Assert\NotNull(message: 'Indiquez la distance totale en Km.')]
    #[Assert\Positive(message: 'La distance totale doit être supérieure à 0 Km.')]
    public ?float $distance = null;

    #[Assert\NotBlank(message: 'Indiquez la durée totale estimée (ex: 11h00).')]
    #[Assert\Regex(
        pattern: '/^(\d+h\d{1,2}|\d+)$/i',
        message: 'Format invalide. Utilisez par exemple 11h00 ou un nombre de minutes (ex: 660).'
    )]
    public ?string $duration = null;

    #[Assert\PositiveOrZero(message: 'Le prix par kilo ne peut pas être négatif.')]
    public ?float $pricePerKg = null;

    #[Assert\NotBlank(message: 'Choisissez la devise du tarif fret.')]
    public ?string $currencyCode = null;

    #[Assert\Positive(message: 'Le délai d\'expiration doit être supérieur à 0 minute.')]
    public ?int $reservationExpirationDelay = 120;

    public bool $isAvailable = true;

    /** @var list<RouteStopData> */
    #[Assert\Valid]
    public array $stops = [];
}
