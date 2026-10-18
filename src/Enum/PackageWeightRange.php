<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Tranches de poids proposées dans le formulaire de recherche de colis.
 */
enum PackageWeightRange: string
{
    case UpTo5 = 'up_to_5';
    case From5To10 = '5_to_10';
    case From10To20 = '10_to_20';
    case Over20 = 'over_20';

    public function label(): string
    {
        return match ($this) {
            self::UpTo5 => 'Moins de 5 kg',
            self::From5To10 => '5 – 10 kg',
            self::From10To20 => '10 – 20 kg',
            self::Over20 => 'Plus de 20 kg',
        };
    }

    /** Poids minimum de la tranche (kg), utilisé pour le tarif « à partir de ». */
    public function minKg(): int
    {
        return match ($this) {
            self::UpTo5 => 1,
            self::From5To10 => 5,
            self::From10To20 => 10,
            self::Over20 => 20,
        };
    }

    /** Poids maximum de la tranche (kg), null pour « plus de 20 kg ». */
    public function maxKg(): ?int
    {
        return match ($this) {
            self::UpTo5 => 5,
            self::From5To10 => 10,
            self::From10To20 => 20,
            self::Over20 => null,
        };
    }

    /** Place à prévoir en soute : on réserve le haut de la tranche. */
    public function requiredCapacityKg(): int
    {
        return $this->maxKg() ?? $this->minKg();
    }
}
