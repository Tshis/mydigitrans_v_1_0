<?php

declare(strict_types=1);

namespace App\Enum;

enum FinancialReferenceType: string
{
    case AgencyPayment = 'agency_payment';
    case Refund = 'refund';
    case Expense = 'expense';
}
