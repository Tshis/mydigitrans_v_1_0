<?php

declare(strict_types=1);

namespace App\Enum;

enum ExpenseType: string
{
    case Subscription = 'subscription';
    case Fuel = 'fuel';
    case Maintenance = 'maintenance';
    case Other = 'other';
}
