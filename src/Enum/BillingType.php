<?php

declare(strict_types=1);

namespace App\Enum;

enum BillingType: string
{
    case Weight = 'weight';
    case Unit = 'unit';
}
