<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Trip;

/**
 * Un voyage (Trip) trouvé pour une recherche, avec les informations propres
 * au tronçon demandé (arrêt de montée et de descente, horaires, prix, places).
 */
final readonly class LineSearchResult
{
    public function __construct(
        public Trip $trip,
        public int $departureStopId,
        public int $arrivalStopId,
        public string $departureCity,
        public string $arrivalCity,
        public \DateTimeImmutable $departureAt,
        public \DateTimeImmutable $arrivalAt,
        public int $availableSeats,
        public int $passengers,
        /** Prix par passager (decimal en chaîne), null si aucun tarif applicable. */
        public ?string $unitPrice,
        public readonly ?string $currency,
        public readonly string $agencyName, // ◄ Ajout du nom de l'agence
        public readonly string $busModel,   // ◄ Ajout du modèle de bus
        public readonly int $durationInMinutes, // ◄ Ajout de la durée en minutes

    ) {}

    /**
     * Prix total pour tous les passagers, pour l'affichage uniquement.
     * Le montant officiel d'une réservation se calcule à la création de la Reservation.
     */
    public function totalPrice(): ?string
    {
        if (null === $this->unitPrice) {
            return null;
        }

        $cents = (int) round(((float) $this->unitPrice) * 100) * $this->passengers;

        return number_format($cents / 100, 2, '.', '');
    }
}
