<?php

declare(strict_types=1);

namespace App\Enum;

enum FeeSourceType: string
{
    case Reservation = 'reservation';
    case Shipment = 'shipment';
}
