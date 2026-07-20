<?php

namespace App\Support;

/**
 * Control Plane copy of StatusColorMap — keep hex values in sync with Core App.
 * Control Plane does not share the Core App codebase.
 */
class StatusColorMap
{
    public const BTN_PRIMARY = '#3E5C82';

    public const ACTIVE = '#22A06B';

    public const POTENTIAL = '#8B5CF6';

    public const INACTIVE = '#D97706';

    public const LEFT = '#DC2626';

    public const NEUTRAL = '#3B82F6';

    public static function defaultThemeColor(): string
    {
        return self::BTN_PRIMARY;
    }

    public static function semantic(string $key): string
    {
        return match (strtolower($key)) {
            'active', 'success' => self::ACTIVE,
            'potential' => self::POTENTIAL,
            'inactive', 'warning', 'grace' => self::INACTIVE,
            'left', 'danger', 'suspended' => self::LEFT,
            default => self::NEUTRAL,
        };
    }
}
