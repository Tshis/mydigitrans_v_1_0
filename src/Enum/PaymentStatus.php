<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Confirmed = 'confirmed';
}
