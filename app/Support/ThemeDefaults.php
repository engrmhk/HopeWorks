<?php

namespace App\Support;

/**
 * Hardcoded fallback theme tokens — used when no DB theme is active,
 * and as the base for Reset to Default / deep merges.
 */
class ThemeDefaults
{
    /** @return array<string, mixed> */
    public static function all(): array
    {
        return [
            'colors' => [
                'light' => self::lightColors(),
                'dark' => self::darkColors(),
            ],
            'typography' => [
                'heading_font' => 'Inter',
                'body_font' => 'Inter',
                'custom_font_path' => null,
                'base_font_size' => 16,
                'font_weight' => 400,
                'line_height' => 1.5,
                'letter_spacing' => 0,
            ],
            'layout' => [
                'border_radius' => 10,
                'button_style' => 'rounded',
                'card_shadow' => 'soft',
                'spacing' => 'comfortable',
                'sidebar_width' => 260,
                'sidebar_position' => 'left',
                'mode' => 'light',
            ],
            'identity' => [
                'app_name' => 'Hope Works',
                'short_name' => 'Hope Works',
                'footer_text' => '',
                'copyright_text' => '© Hope Works. All rights reserved.',
                'copyright_url' => 'https://hopeworks.app',
            ],
            'logos' => [
                'main_logo' => null,
                'login_logo' => null,
                'favicon' => null,
            ],
            'login_page' => [
                'background_image' => null,
                'background_color' => '#E8EEF6',
                'welcome_title' => 'Welcome back',
                'welcome_subtitle' => 'Sign in to the Hope Works Control Plane',
                'button_color' => StatusColorMap::BTN_PRIMARY,
                'button_text_color' => '#FFFFFF',
            ],
            'navigation' => [
                'sidebar_color' => StatusColorMap::SIDEBAR_BG,
                'active_menu_color' => '#2B4066',
                'menu_hover_color' => '#2B4066',
                'top_nav_color' => StatusColorMap::CARD_BG,
                'icon_color' => '#8C9BB5',
            ],
            'tables' => [
                'header_color' => '#F8FAFC',
                'alt_row_color' => '#F8FAFC',
                'hover_color' => '#F1F5F9',
                'border_color' => '#E5E7EB',
            ],
            'forms' => [
                'input_bg' => '#FFFFFF',
                'input_border' => '#CBD5E1',
                'focus_color' => StatusColorMap::BTN_PRIMARY,
                'label_color' => '#0F172A',
                'required_color' => StatusColorMap::LEFT,
            ],
            'buttons' => [
                'primary' => StatusColorMap::BTN_PRIMARY,
                'secondary' => '#64748B',
                'success' => StatusColorMap::BTN_SUCCESS,
                'warning' => StatusColorMap::INACTIVE,
                'danger' => StatusColorMap::LEFT,
                'disabled' => '#94A3B8',
                'hover' => StatusColorMap::BTN_PRIMARY_HOVER,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function lightColors(): array
    {
        return [
            'primary' => StatusColorMap::BTN_PRIMARY,
            'secondary' => '#64748B',
            'accent' => StatusColorMap::POTENTIAL,
            'success' => StatusColorMap::ACTIVE,
            'warning' => StatusColorMap::INACTIVE,
            'danger' => StatusColorMap::LEFT,
            'background' => StatusColorMap::CANVAS_BG,
            'surface' => StatusColorMap::CARD_BG,
            'header_bg' => StatusColorMap::CARD_BG,
            'sidebar_bg' => StatusColorMap::SIDEBAR_BG,
            'sidebar_active' => '#2B4066',
            'text_primary' => '#0F172A',
            'text_secondary' => '#64748B',
            'border' => '#E5E7EB',
            'link' => StatusColorMap::NEUTRAL,
            'button_primary' => StatusColorMap::BTN_PRIMARY,
            'button_secondary' => '#64748B',
            'button_hover' => StatusColorMap::BTN_PRIMARY_HOVER,
            'input_border' => '#CBD5E1',
            'input_focus' => StatusColorMap::BTN_PRIMARY,
        ];
    }

    /** @return array<string, string> */
    public static function darkColors(): array
    {
        return [
            'primary' => '#5B7AA5',
            'secondary' => '#94A3B8',
            'accent' => '#A78BFA',
            'success' => '#34D399',
            'warning' => '#FBBF24',
            'danger' => '#F87171',
            'background' => '#0B1220',
            'surface' => '#1A2740',
            'header_bg' => '#152033',
            'sidebar_bg' => '#020617',
            'sidebar_active' => '#1E293B',
            'text_primary' => '#F1F5F9',
            'text_secondary' => '#94A3B8',
            'border' => '#2A3B55',
            'link' => '#60A5FA',
            'button_primary' => '#5B7AA5',
            'button_secondary' => '#94A3B8',
            'button_hover' => '#6B8BB8',
            'input_border' => '#334155',
            'input_focus' => '#5B7AA5',
        ];
    }

    /** @return list<string> */
    public static function colorKeys(): array
    {
        return array_keys(self::lightColors());
    }

    /** @return array<string, string> */
    public static function shadowMap(): array
    {
        return [
            'none' => 'none',
            'soft' => '0 1px 3px rgba(15, 23, 42, 0.06)',
            'medium' => '0 4px 12px rgba(15, 23, 42, 0.08)',
            'strong' => '0 16px 40px rgba(15, 23, 42, 0.14)',
        ];
    }

    /** @return array<string, string> */
    public static function darkShadowMap(): array
    {
        return [
            'none' => 'none',
            'soft' => '0 1px 3px rgba(0, 0, 0, 0.4)',
            'medium' => '0 4px 14px rgba(0, 0, 0, 0.5)',
            'strong' => '0 16px 40px rgba(0, 0, 0, 0.65)',
        ];
    }
}
