<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DurationExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // Déclaration du filtre 'duration'
            new TwigFilter('duration', [$this, 'formatDuration']),
        ];
    }

    /**
     * Convertit un nombre total de minutes en format "Xh XXmin" ou "Xh"
     */
    public function formatDuration(int $totalMinutes): string
    {
        if ($totalMinutes <= 0) {
            return '0min';
        }

        $hours = $totalMinutes / 60; // 60;
        $minutes = $totalMinutes % 60;

        if ($hours === 0) {
            return sprintf('%dmin', $minutes);
        }

        if ($minutes === 0) {
            return sprintf('%dh', $hours);
        }

        // Retourne par exemple : 5h 20min ou 2h 05min
        return sprintf('%dh %02dmin', $hours, $minutes);
    }
}
