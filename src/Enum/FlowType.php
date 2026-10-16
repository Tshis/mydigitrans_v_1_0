<?php

declare(strict_types=1);

namespace App\Enum;

enum FlowType: string
{
    case In = 'in';
    case Out = 'out';
}
