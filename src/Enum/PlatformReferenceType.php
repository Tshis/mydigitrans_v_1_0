<?php

declare(strict_types=1);

namespace App\Enum;

enum PlatformReferenceType: string
{
    case Invoice = 'invoice';
    case Refund = 'refund';
    case CommissionPayment = 'commission_payment';
    case PlatformExpense = 'platform_expense';
}
