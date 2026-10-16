<?php

declare(strict_types=1);

namespace App\Enum;

enum TripStatus: string
{
    case Scheduled = 'scheduled';
    case Boarding = 'boarding';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';
}
