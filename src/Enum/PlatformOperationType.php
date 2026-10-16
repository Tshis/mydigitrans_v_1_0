<?php

declare(strict_types=1);

namespace App\Enum;

enum PlatformOperationType: string
{
    case Commission = 'commission';
    case SubscriptionPayment = 'subscription_payment';
    case Refund = 'refund';
    case Expense = 'expense';
    case Adjustment = 'adjustment';
}
