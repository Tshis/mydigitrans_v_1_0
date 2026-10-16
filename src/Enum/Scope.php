<?php

declare(strict_types=1);

namespace App\Enum;

enum Scope: string
{
    case Platform = 'platform';
    case Agency = 'agency';
}
