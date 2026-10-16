<?php

declare(strict_types=1);

namespace App\Enum;

enum PlatformFeeStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
}
