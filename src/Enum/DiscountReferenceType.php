<?php

declare(strict_types=1);

namespace App\Enum;

enum DiscountReferenceType: string
{
    case Reservation = 'reservation';
    case Shipment = 'shipment';
}
