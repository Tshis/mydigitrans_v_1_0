<?php

declare(strict_types=1);

namespace App\Enum;

enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
