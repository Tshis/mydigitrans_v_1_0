<?php

declare(strict_types=1);

namespace App\Enum;

enum SeatType: string
{
    case Driver = 'driver';
    case Normal = 'normal';
    case Vip = 'vip';
    case Extra = 'extra';
    case coDriver = 'coDriver';
    case Wc = 'Wc';
}
