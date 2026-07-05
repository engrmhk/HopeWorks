<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Grace => 'Grace Period',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }
}
