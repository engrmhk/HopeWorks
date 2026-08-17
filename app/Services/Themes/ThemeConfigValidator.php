<?php

namespace App\Services\Themes;

use App\Support\ThemeDefaults;
use Illuminate\Validation\ValidationException;

class ThemeConfigValidator
{
    /** @param  array<string, mixed>  $config */
    public function validate(array $config): array
    {
        $allowedTop = array_keys(ThemeDefaults::all());
        $unknownTop = array_diff(array_keys($config), $allowedTop);

        if ($unknownTop !== []) {
            throw ValidationException::withMessages([
                'config' => 'Unknown theme keys: '.implode(', ', $unknownTop),
            ]);
        }

        $sanitized = [];

        foreach ($allowedTop as $section) {
            if (! array_key_exists($section, $config)) {
                continue;
            }

            if (! is_array($config[$section])) {
                throw ValidationException::withMessages([
                    'config' => "Section [{$section}] must be an object.",
                ]);
            }

            $sanitized[$section] = $this->sanitizeSection($section, $config[$section]);
        }

        return $sanitized;
    }

    /** @param  array<string, mixed>  $section */
    protected function sanitizeSection(string $name, array $section): array
    {
        $defaults = ThemeDefaults::all()[$name] ?? [];

        return match ($name) {
            'colors' => $this->sanitizeColors($section),
            'typography' => $this->sanitizeTypography($section, $defaults),
            'layout' => $this->sanitizeLayout($section, $defaults),
            'identity' => $this->sanitizeStringMap($section, $defaults, 255),
            'logos' => $this->sanitizeNullableStrings($section, $defaults),
            'login_page' => $this->sanitizeLoginPage($section, $defaults),
            'navigation', 'tables', 'forms', 'buttons' => $this->sanitizeKeyedColors($section, $defaults),
            default => [],
        };
    }

    /** @param  array<string, mixed>  $colors */
    protected function sanitizeColors(array $colors): array
    {
        $out = [];
        foreach (['light', 'dark'] as $mode) {
            if (! isset($colors[$mode]) || ! is_array($colors[$mode])) {
                continue;
            }
            $out[$mode] = [];
            foreach (ThemeDefaults::colorKeys() as $key) {
                if (! array_key_exists($key, $colors[$mode])) {
                    continue;
                }
                $out[$mode][$key] = $this->assertColor((string) $colors[$mode][$key], "colors.{$mode}.{$key}");
            }
            $unknown = array_diff(array_keys($colors[$mode]), ThemeDefaults::colorKeys());
            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'config' => "Unknown color keys in {$mode}: ".implode(', ', $unknown),
                ]);
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $section @param  array<string, mixed>  $defaults */
    protected function sanitizeTypography(array $section, array $defaults): array
    {
        $out = [];
        $allowed = array_keys($defaults);
        $unknown = array_diff(array_keys($section), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['config' => 'Unknown typography keys: '.implode(', ', $unknown)]);
        }

        if (isset($section['heading_font'])) {
            $out['heading_font'] = substr(strip_tags((string) $section['heading_font']), 0, 80);
        }
        if (isset($section['body_font'])) {
            $out['body_font'] = substr(strip_tags((string) $section['body_font']), 0, 80);
        }
        if (array_key_exists('custom_font_path', $section)) {
            $out['custom_font_path'] = $section['custom_font_path'] !== null
                ? substr(strip_tags((string) $section['custom_font_path']), 0, 255)
                : null;
        }
        if (isset($section['base_font_size'])) {
            $out['base_font_size'] = $this->assertIntRange((int) $section['base_font_size'], 12, 24, 'base_font_size');
        }
        if (isset($section['font_weight'])) {
            $out['font_weight'] = $this->assertIntRange((int) $section['font_weight'], 100, 900, 'font_weight');
        }
        if (isset($section['line_height'])) {
            $out['line_height'] = $this->assertFloatRange((float) $section['line_height'], 1.0, 2.5, 'line_height');
        }
        if (isset($section['letter_spacing'])) {
            $out['letter_spacing'] = $this->assertFloatRange((float) $section['letter_spacing'], -0.1, 0.2, 'letter_spacing');
        }

        return $out;
    }

    /** @param  array<string, mixed>  $section @param  array<string, mixed>  $defaults */
    protected function sanitizeLayout(array $section, array $defaults): array
    {
        $unknown = array_diff(array_keys($section), array_keys($defaults));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['config' => 'Unknown layout keys: '.implode(', ', $unknown)]);
        }

        $out = [];
        if (isset($section['border_radius'])) {
            $out['border_radius'] = $this->assertIntRange((int) $section['border_radius'], 0, 32, 'border_radius');
        }
        if (isset($section['button_style'])) {
            $style = (string) $section['button_style'];
            if (! in_array($style, ['rounded', 'pill', 'square'], true)) {
                throw ValidationException::withMessages(['config' => 'Invalid button_style.']);
            }
            $out['button_style'] = $style;
        }
        if (isset($section['card_shadow'])) {
            $shadow = (string) $section['card_shadow'];
            if (! in_array($shadow, ['none', 'soft', 'medium', 'strong'], true)) {
                throw ValidationException::withMessages(['config' => 'Invalid card_shadow.']);
            }
            $out['card_shadow'] = $shadow;
        }
        if (isset($section['spacing'])) {
            $spacing = (string) $section['spacing'];
            if (! in_array($spacing, ['compact', 'comfortable'], true)) {
                throw ValidationException::withMessages(['config' => 'Invalid spacing.']);
            }
            $out['spacing'] = $spacing;
        }
        if (isset($section['sidebar_width'])) {
            $out['sidebar_width'] = $this->assertIntRange((int) $section['sidebar_width'], 200, 360, 'sidebar_width');
        }
        if (isset($section['sidebar_position'])) {
            $pos = (string) $section['sidebar_position'];
            if (! in_array($pos, ['left', 'right'], true)) {
                throw ValidationException::withMessages(['config' => 'Invalid sidebar_position.']);
            }
            $out['sidebar_position'] = $pos;
        }
        if (isset($section['mode'])) {
            $mode = (string) $section['mode'];
            if (! in_array($mode, ['light', 'dark', 'auto'], true)) {
                throw ValidationException::withMessages(['config' => 'Invalid layout.mode.']);
            }
            $out['mode'] = $mode;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $section @param  array<string, mixed>  $defaults */
    protected function sanitizeLoginPage(array $section, array $defaults): array
    {
        $unknown = array_diff(array_keys($section), array_keys($defaults));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['config' => 'Unknown login_page keys: '.implode(', ', $unknown)]);
        }

        $out = [];
        foreach (['background_image'] as $key) {
            if (array_key_exists($key, $section)) {
                $out[$key] = $section[$key] !== null ? substr(strip_tags((string) $section[$key]), 0, 255) : null;
            }
        }
        foreach (['welcome_title', 'welcome_subtitle'] as $key) {
            if (isset($section[$key])) {
                $out[$key] = substr(strip_tags((string) $section[$key]), 0, 255);
            }
        }
        foreach (['background_color', 'button_color', 'button_text_color'] as $key) {
            if (isset($section[$key])) {
                $out[$key] = $this->assertColor((string) $section[$key], "login_page.{$key}");
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $section @param  array<string, mixed>  $defaults */
    protected function sanitizeKeyedColors(array $section, array $defaults): array
    {
        $unknown = array_diff(array_keys($section), array_keys($defaults));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['config' => 'Unknown keys: '.implode(', ', $unknown)]);
        }

        $out = [];
        foreach ($section as $key => $value) {
            $out[$key] = $this->assertColor((string) $value, (string) $key);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $section @param  array<string, mixed>  $defaults */
    protected function sanitizeStringMap(array $section, array $defaults, int $max): array
    {
        $unknown = array_diff(array_keys($section), array_keys($defaults));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['config' => 'Unknown identity keys: '.implode(', ', $unknown)]);
        }

        $out = [];
        foreach ($section as $key => $value) {
            $out[$key] = substr(strip_tags((string) $value), 0, $max);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $section @param  array<string, mixed>  $defaults */
    protected function sanitizeNullableStrings(array $section, array $defaults): array
    {
        $unknown = array_diff(array_keys($section), array_keys($defaults));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['config' => 'Unknown logo keys: '.implode(', ', $unknown)]);
        }

        $out = [];
        foreach ($section as $key => $value) {
            $out[$key] = $value !== null ? substr(strip_tags((string) $value), 0, 255) : null;
        }

        return $out;
    }

    protected function assertColor(string $value, string $field): string
    {
        $value = trim($value);
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(0|0?\.\d+|1(\.0)?))?\s*\)$/', $value) === 1) {
            return $value;
        }

        throw ValidationException::withMessages([
            'config' => "Invalid color for [{$field}]: {$value}",
        ]);
    }

    protected function assertIntRange(int $value, int $min, int $max, string $field): int
    {
        if ($value < $min || $value > $max) {
            throw ValidationException::withMessages([
                'config' => "[{$field}] must be between {$min} and {$max}.",
            ]);
        }

        return $value;
    }

    protected function assertFloatRange(float $value, float $min, float $max, string $field): float
    {
        if ($value < $min || $value > $max) {
            throw ValidationException::withMessages([
                'config' => "[{$field}] must be between {$min} and {$max}.",
            ]);
        }

        return $value;
    }
}
