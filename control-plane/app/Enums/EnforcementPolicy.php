<?php

namespace App\Enums;

enum EnforcementPolicy: string
{
    case BannerOnly = 'banner_only';
    case ReadOnly = 'read_only';
    case FullLock = 'full_lock';

    public function label(): string
    {
        return match ($this) {
            self::BannerOnly => 'Banner Only',
            self::ReadOnly => 'Read Only',
            self::FullLock => 'Full Lock',
        };
    }
}
