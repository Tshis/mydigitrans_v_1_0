<?php

declare(strict_types=1);

namespace App\Dto\public\package;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Trip;

/**
 * Un départ (Trip) sur lequel un colis peut être confié, avec les guichets
 * de dépôt et de retrait, les horaires aux gares demandées et une estimation du tarif.
 */
final readonly class PackageSearchResult
{
    public function __construct(
        public Trip $trip,
        public Agency $agency,
        public string $busModel,
        /** Guichet de la gare de départ où déposer le colis. */
        public Branch $departureBranch,
        /** Guichet de la gare d'arrivée où le destinataire retire le colis. */
        public Branch $arrivalBranch,
        public \DateTimeImmutable $departureAt,
        public \DateTimeImmutable $arrivalAt,
        public int $remainingCapacityKg,
        /** Tarif estimé minimum (decimal en chaîne), null si la route n'a pas de prix au kilo. */
        public ?string $minFare,
        /** Tarif estimé maximum, null pour « plus de 20 kg » ou sans prix au kilo. */
        public ?string $maxFare,
        public ?string $currency,
    ) {}

    /** Durée du trajet entre les deux gares, par exemple « 6 h 00 ». */
    public function durationLabel(): string
    {
        $minutes = max(0, intdiv($this->arrivalAt->getTimestamp() - $this->departureAt->getTimestamp(), 60));

        return sprintf('%d h %02d', intdiv($minutes, 60), $minutes % 60);
    }
}
