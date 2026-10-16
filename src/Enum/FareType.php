<?php

declare(strict_types=1);

namespace App\Enum;

enum FareType: string
{
    case Promo = 'promo';
    case Standard = 'standard';
    case Vip = 'vip';
}
