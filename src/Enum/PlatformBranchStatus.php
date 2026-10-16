<?php

declare(strict_types=1);

namespace App\Enum;

enum PlatformBranchStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
