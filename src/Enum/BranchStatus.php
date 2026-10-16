<?php

declare(strict_types=1);

namespace App\Enum;

enum BranchStatus: string
{
    case Opened = 'opened';
    case Closed = 'closed';
    case Suspended = 'suspended';
}
