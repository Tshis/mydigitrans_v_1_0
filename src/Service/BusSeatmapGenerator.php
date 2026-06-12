<?php
// src/Service/BusSeatmapGenerator.php

namespace App\Service;

class BusSeatmapGenerator
{
    /**
     * Prépare les données de configuration géométrique d'un bus pour le Seatmap Twig.
     * Compatible à 100% avec les entités de ton MCD (Bus, BusLayout, Seat, Ticket).
     */
    public function prepareSeatmapData($bus, array $bookedSeatIds = []): array
    {
        // 1. Extraction du gabarit busLayout (Données issues de ton MCD)
        $layout = $bus->getBusLayout();

        $leftCols = $layout->getLeftColumns();
        $rightCols = $layout->getRightColumns();
        $middleCols = $layout->getMiddleColumns(); // Souvent 1 pour l'allée centrale

        $totalColumns = $leftCols + $rightCols + $middleCols;

        // 2. Traitement des sièges liés au bus
        $seatsData = [];
        foreach ($bus->getSeats() as $seat) {
            // Un siège est marqué occupé s'il est dans la liste des billets déjà vendus pour ce trajet
            $isBooked = in_array($seat->getId(), $bookedSeatIds);

            $seatsData[] = [
                'id' => $seat->getId(),
                'row' => $seat->getRow(),
                'column' => $seat->getColumn(),
                'type' => $seat->getType(), // normal, vip, driver, wc, door, aisle
                'seat_label' => $seat->getSeatLabel(),
                'is_booked' => $isBooked
            ];
        }

        return [
            'total_columns' => $totalColumns,
            'seats' => $seatsData
        ];
    }
}
