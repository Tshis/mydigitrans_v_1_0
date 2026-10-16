<?php

declare(strict_types=1);

namespace App\Enum;

enum ProcessStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
