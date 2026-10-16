<?php

declare(strict_types=1);

namespace App\Enum;

enum CashSessionStatus: string
{
    case Opened = 'opened';
    case Closed = 'closed';
    case Pending = 'pending';
}
