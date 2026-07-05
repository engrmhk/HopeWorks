<?php

namespace App\Enums;

enum ChurchStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Provisioning = 'provisioning';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Provisioning => 'Provisioning',
        };
    }
}
