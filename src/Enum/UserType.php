<?php

declare(strict_types=1);

namespace App\Enum;

enum UserType: string
{
    case Platform = 'platform';
    case Agency = 'agency';
    case Partner = 'partner';
    case Client = 'client';
}
