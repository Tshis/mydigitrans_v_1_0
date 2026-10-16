<?php

declare(strict_types=1);

namespace App\Enum;

enum PaidByType: string
{
    case Agency = 'agency';
    case Platform = 'platform';
}
