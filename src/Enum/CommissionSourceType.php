<?php

declare(strict_types=1);

namespace App\Enum;

enum CommissionSourceType: string
{
    case Ticket = 'ticket';
    case Shipment = 'shipment';
    case Subscription = 'subscription';
}
