<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketStatus: string
{
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Used = 'used';
}
