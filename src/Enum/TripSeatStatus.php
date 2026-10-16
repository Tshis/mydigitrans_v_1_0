<?php

declare(strict_types=1);

namespace App\Enum;

enum TripSeatStatus: string
{
    case Held = 'held';
    case Reserved = 'reserved';
    case Blocked = 'blocked';
    case Available = 'available';
}
