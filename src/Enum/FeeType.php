<?php

declare(strict_types=1);

namespace App\Enum;

enum FeeType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';
}
