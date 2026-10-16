<?php

declare(strict_types=1);

namespace App\Enum;

enum InvoiceType: string
{
    case Subscription = 'subscription';
    case PlatformFee = 'platform_fee';
    case Mixed = 'mixed';
}
