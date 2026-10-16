<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Cancel = 'cancel';
    case Refund = 'refund';
    case Login = 'login';
    case Delete = 'delete';
    case ConfirmPayment = 'confirm_payment';
    case CancelBooking = 'cancel_booking';
}
