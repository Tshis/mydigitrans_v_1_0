<?php

declare(strict_types=1);

namespace App\Enum;

enum FareTargetType: string
{
    case Route = 'route';
    case SubscriptionPlan = 'subscription_plan';
    case Feature = 'feature';
}
