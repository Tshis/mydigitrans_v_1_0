<?php

declare(strict_types=1);

namespace App\Enum;

enum MaintenanceStatus: string
{
    case Broken = 'broken';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
}
