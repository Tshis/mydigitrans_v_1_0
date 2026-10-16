<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionPaymentMethod: string
{
    case Manual = 'manual';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
}
