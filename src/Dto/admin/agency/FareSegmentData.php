<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente la tarification d'un tronçon intermédiaire (departureStop -> arrivalStop).
 */
final class FareSegmentData
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $fromStopId = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $toStopId = null;

    /** Libellés informatifs en lecture seule pour l'affichage dans le tableau. */
    public string $fromCity = '';
    public string $toCity = '';
    public float $distance = 0.0;

    #[Assert\PositiveOrZero(message: 'Le prix du billet ne peut pas être négatif.')]
    public ?float $amount = null;

    #[Assert\PositiveOrZero(message: 'Le prix du fret ne peut pas être négatif.')]
    public ?float $pricePerKg = null;

    public bool $isActive = true;
}
