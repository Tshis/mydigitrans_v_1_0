<?php

declare(strict_types=1);

namespace App\Enum;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
