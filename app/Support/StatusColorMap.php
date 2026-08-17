<?php

namespace App\Support;

/**
 * Control Plane copy of StatusColorMap — keep hex values in sync with Core App.
 * Control Plane does not share the Core App codebase.
 */
class StatusColorMap
{
    public const SIDEBAR_BG = '#1B2942';

    public const BTN_PRIMARY = '#3E5C82';

    public const BTN_PRIMARY_HOVER = '#33496A';

    public const BTN_SUCCESS = '#16A34A';

    public const CANVAS_BG = '#F4F6F9';

    public const CARD_BG = '#FFFFFF';

    public const ACTIVE = '#22A06B';

    public const POTENTIAL = '#8B5CF6';

    public const INACTIVE = '#D97706';

    public const LEFT = '#DC2626';

    public const NEUTRAL = '#3B82F6';

    public static function defaultThemeColor(): string
    {
        return self::resolvedStatusColors()['primary'];
    }

    public static function semantic(string $key): string
    {
        $colors = self::resolvedStatusColors();

        return match (strtolower($key)) {
            'active', 'success' => $colors['active'],
            'potential' => $colors['potential'],
            'inactive', 'warning', 'grace' => $colors['inactive'],
            'left', 'danger', 'suspended' => $colors['left'],
            default => $colors['neutral'],
        };
    }

    /**
     * @return array{active: string, potential: string, inactive: string, left: string, neutral: string, success: string, warning: string, danger: string, accent: string, primary: string}
     */
    public static function resolvedStatusColors(): array
    {
        try {
            return app(\App\Services\Themes\ThemeCompiler::class)->statusColors();
        } catch (\Throwable) {
            return [
                'active' => self::ACTIVE,
                'potential' => self::POTENTIAL,
                'inactive' => self::INACTIVE,
                'left' => self::LEFT,
                'neutral' => self::NEUTRAL,
                'success' => self::ACTIVE,
                'warning' => self::INACTIVE,
                'danger' => self::LEFT,
                'accent' => self::POTENTIAL,
                'primary' => self::BTN_PRIMARY,
            ];
        }
    }
}
