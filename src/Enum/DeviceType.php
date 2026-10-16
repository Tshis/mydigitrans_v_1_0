<?php

declare(strict_types=1);

namespace App\Enum;

enum DeviceType: string
{
    case Mobile = 'mobile';
    case Pos = 'pos';
    case Desktop = 'desktop';
}
