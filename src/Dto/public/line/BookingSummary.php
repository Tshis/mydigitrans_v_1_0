<?php

declare(strict_types=1);

namespace App\Dto\public\line;

/**
 * Données d'une réservation prêtes à être affichées (récapitulatif, bon de réservation).
 */
final readonly class BookingSummary
{
    /**
     * @param list<string> $seats libellés des sièges
     */
    public function __construct(
        public int $id,
        public string $reference,
        /** Valeur de ReservationStatus : pending, paid, cancelled, expired. */
        public string $status,
        public string $tripCode,
        public string $routeLabel,
        public string $agencyName,
        public string $departureCity,
        public string $arrivalCity,
        public string $busModel,
        public \DateTimeImmutable $departureAt,
        public ?\DateTimeImmutable $expiresAt,
        public string $passengerName,
        public string $passengerPhone,
        public array $seats,
        /** « VIP » si au moins un siège VIP, sinon « Standard ». */
        public string $fareCategory,
        public string $total,
        public string $currencyCode,
    ) {}
}
