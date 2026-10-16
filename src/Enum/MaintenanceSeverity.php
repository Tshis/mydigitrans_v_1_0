<?php

declare(strict_types=1);

namespace App\Enum;

enum MaintenanceSeverity: string
{
    case Critical = 'critical';
    case Medium = 'medium';
    case Low = 'low';
}
