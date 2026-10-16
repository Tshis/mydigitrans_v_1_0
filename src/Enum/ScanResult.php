<?php

declare(strict_types=1);

namespace App\Enum;

enum ScanResult: string
{
    case Valid = 'valid';
    case AlreadyUsed = 'already_used';
    case Invalid = 'invalid';
}
