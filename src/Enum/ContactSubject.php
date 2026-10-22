<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Objets proposés dans le formulaire de contact.
 */
enum ContactSubject: string
{
    case TechnicalSupport = 'technical_support';
    case Partnership = 'partnership';
    case Commercial = 'commercial';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TechnicalSupport => 'Support technique',
            self::Partnership => 'Partenariat',
            self::Commercial => 'Demande commerciale',
            self::Other => 'Autre',
        };
    }
}
