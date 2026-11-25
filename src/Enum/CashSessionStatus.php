<?php

declare(strict_types=1);

namespace App\Enum;

enum CashSessionStatus: string
{
    case Pending        = 'pending';
    case Opened         = 'opened';
    case ClosingPending = 'closing_pending';
    case Closed         = 'closed';
}
