<?php

declare(strict_types=1);

namespace App\Enum;

enum FundTransferStatus: string
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Recieved = 'recieved';
}
