<?php

namespace App\Enums;

enum InvoiceSource: string
{
    case Stripe = 'stripe';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::Manual => 'Manual',
        };
    }
}
