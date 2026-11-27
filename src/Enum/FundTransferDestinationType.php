<?php

declare(strict_types=1);

namespace App\Enum;

enum FundTransferDestinationType: string
{
    case Bank = 'bank';
    case Branch = 'branch';
}
