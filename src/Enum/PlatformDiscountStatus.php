<?php

declare(strict_types=1);

namespace App\Enum;

enum PlatformDiscountStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
