<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentType: string
{
    case Reservation = 'reservation';
    case Shipment = 'shipment';
}
