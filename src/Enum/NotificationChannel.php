<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case InApp = 'in_app';
}
