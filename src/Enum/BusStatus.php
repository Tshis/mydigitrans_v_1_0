<?php

declare(strict_types=1);

namespace App\Enum;

enum BusStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';
}
