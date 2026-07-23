<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case CardManual = 'card_manual';
    case Stripe = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::CardManual => 'Card (recorded manually)',
            self::Stripe => 'Stripe',
        };
    }
}
