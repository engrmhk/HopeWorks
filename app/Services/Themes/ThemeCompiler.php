<?php

namespace App\Services\Themes;

use App\Support\BrandingAsset;
use App\Support\ThemeDefaults;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ThemeCompiler
{
    public function __construct(
        protected ThemeResolver $resolver,
    ) {}

    public function cacheKey(): string
    {
        return 'theme.css.v1.control-plane';
    }

    public function pdfCacheKey(): string
    {
        return 'theme.pdf.v1.control-plane';
    }

    public function invalidate(): void
    {
        Cache::forget($this->cacheKey());
        Cache::forget($this->pdfCacheKey());
    }

    public function compileCss(?array $draftConfig = null): string
    {
        if ($draftConfig !== null) {
            return $this->buildCssFromConfig(array_replace_recursive(ThemeDefaults::all(), $draftConfig));
        }

        return Cache::rememberForever($this->cacheKey(), function (): string {
            return $this->buildCssFromConfig($this->resolver->resolvedConfig());
        });
    }

    /**
     * @return array<string, string>
     */
    public function resolveForPdf(?array $draftConfig = null): array
    {
        if ($draftConfig !== null) {
            return $this->buildPdfMap(array_replace_recursive(ThemeDefaults::all(), $draftConfig));
        }

        return Cache::rememberForever($this->pdfCacheKey(), function (): array {
            return $this->buildPdfMap($this->resolver->resolvedConfig());
        });
    }

    /**
     * @return array{active: string, potential: string, inactive: string, left: string, neutral: string, success: string, warning: string, danger: string, accent: string, primary: string}
     */
    public function statusColors(): array
    {
        $config = $this->resolver->resolvedConfig();
        $light = Arr::get($config, 'colors.light', ThemeDefaults::lightColors());

        return [
            'active' => (string) ($light['success'] ?? ThemeDefaults::lightColors()['success']),
            'potential' => (string) ($light['accent'] ?? ThemeDefaults::lightColors()['accent']),
            'inactive' => (string) ($light['warning'] ?? ThemeDefaults::lightColors()['warning']),
            'left' => (string) ($light['danger'] ?? ThemeDefaults::lightColors()['danger']),
            'neutral' => (string) ($light['link'] ?? ThemeDefaults::lightColors()['link']),
            'success' => (string) ($light['success'] ?? ThemeDefaults::lightColors()['success']),
            'warning' => (string) ($light['warning'] ?? ThemeDefaults::lightColors()['warning']),
            'danger' => (string) ($light['danger'] ?? ThemeDefaults::lightColors()['danger']),
            'accent' => (string) ($light['accent'] ?? ThemeDefaults::lightColors()['accent']),
            'primary' => (string) ($light['primary'] ?? ThemeDefaults::lightColors()['primary']),
        ];
    }

    /** @param  array<string, mixed>  $config */
    protected function buildCssFromConfig(array $config): string
    {
        $light = Arr::get($config, 'colors.light', ThemeDefaults::lightColors());
        $dark = Arr::get($config, 'colors.dark', ThemeDefaults::darkColors());
        $layout = Arr::get($config, 'layout', []);
        $typography = Arr::get($config, 'typography', []);
        $nav = Arr::get($config, 'navigation', []);
        $forms = Arr::get($config, 'forms', []);
        $buttons = Arr::get($config, 'buttons', []);
        $tables = Arr::get($config, 'tables', []);
        $login = Arr::get($config, 'login_page', ThemeDefaults::all()['login_page']);
        $logos = Arr::get($config, 'logos', []);

        $lightPrimary = $light['primary'] ?? ThemeDefaults::lightColors()['primary'];
        $darkPrimary = $dark['primary'] ?? ThemeDefaults::darkColors()['primary'];
        $light['button_primary'] = $lightPrimary;
        $dark['button_primary'] = $darkPrimary;
        $buttons['primary'] = $lightPrimary;
        $forms['focus_color'] = $lightPrimary;

        $radius = (int) ($layout['border_radius'] ?? 10);
        $spacing = (string) ($layout['spacing'] ?? 'comfortable');
        $shadowKey = (string) ($layout['card_shadow'] ?? 'soft');
        $mode = (string) ($layout['mode'] ?? 'light');
        $sidebarWidth = (int) ($layout['sidebar_width'] ?? 260);
        $baseFont = (int) ($typography['base_font_size'] ?? 16);
        $lineHeight = (float) ($typography['line_height'] ?? 1.5);
        $letterSpacing = (float) ($typography['letter_spacing'] ?? 0);
        $headingFont = (string) ($typography['heading_font'] ?? 'Inter');
        $bodyFont = (string) ($typography['body_font'] ?? 'Inter');

        $spacingScale = match ($spacing) {
            'compact' => 0.85,
            default => 1.0,
        };

        $buttonRadius = match ((string) ($layout['button_style'] ?? 'rounded')) {
            'pill' => '999px',
            'square' => '2px',
            default => $radius.'px',
        };

        $lightShadow = ThemeDefaults::shadowMap()[$shadowKey] ?? ThemeDefaults::shadowMap()['soft'];
        $darkShadow = ThemeDefaults::darkShadowMap()[$shadowKey] ?? ThemeDefaults::darkShadowMap()['soft'];

        $root = $this->colorBlockToHwVars($light, [
            '--hw-card-radius' => $radius.'px',
            '--hw-button-radius' => $buttonRadius,
            '--hw-sidebar-width' => $sidebarWidth.'px',
            '--hw-font-heading' => "'{$headingFont}', ui-sans-serif, system-ui, sans-serif",
            '--hw-font-body' => "'{$bodyFont}', ui-sans-serif, system-ui, sans-serif",
            '--hw-font-size-base' => $baseFont.'px',
            '--hw-line-height' => (string) $lineHeight,
            '--hw-letter-spacing' => $letterSpacing.'em',
            '--hw-space-scale' => (string) $spacingScale,
            '--hw-shadow-1' => $lightShadow,
            '--hw-shadow-2' => ThemeDefaults::shadowMap()['medium'],
            '--hw-shadow-3' => ThemeDefaults::shadowMap()['strong'],
            '--hw-btn-primary' => $lightPrimary,
            '--hw-btn-primary-hover' => $buttons['hover'] ?? $light['button_hover'] ?? $lightPrimary,
            '--hw-btn-success' => $buttons['success'] ?? $light['success'],
            '--hw-btn-danger-outline' => $buttons['danger'] ?? $light['danger'],
            '--hw-input-bg' => $forms['input_bg'] ?? '#FFFFFF',
            '--hw-input-border' => $forms['input_border'] ?? $light['input_border'],
            '--hw-table-header' => $tables['header_color'] ?? '#F8FAFC',
            '--hw-table-alt' => $tables['alt_row_color'] ?? '#F8FAFC',
            '--hw-table-hover' => $tables['hover_color'] ?? '#F1F5F9',
            '--hw-table-border' => $tables['border_color'] ?? $light['border'],
            '--hw-nav-top' => $nav['top_nav_color'] ?? $light['header_bg'],
            '--hw-nav-icon' => $nav['icon_color'] ?? '#8C9BB5',
            '--hw-status-active' => $light['success'],
            '--hw-status-potential' => $light['accent'],
            '--hw-status-inactive' => $light['warning'],
            '--hw-status-left' => $light['danger'],
            '--hw-status-neutral' => $light['link'],
            '--hw-login-bg' => (string) ($login['background_color'] ?? $light['background'] ?? '#F4F6F9'),
            '--hw-login-btn' => (string) ($login['button_color'] ?? $lightPrimary),
            '--hw-login-btn-text' => (string) ($login['button_text_color'] ?? '#FFFFFF'),
            '--hw-login-bg-image' => $this->cssBackgroundImage($login['background_image'] ?? null),
            '--hw-login-logo' => $this->cssBackgroundImage($logos['login_logo'] ?? $logos['main_logo'] ?? null),
        ], $nav);

        $darkBlock = $this->colorBlockToHwVars($dark, [
            '--hw-shadow-1' => $darkShadow,
            '--hw-shadow-2' => ThemeDefaults::darkShadowMap()['medium'],
            '--hw-shadow-3' => ThemeDefaults::darkShadowMap()['strong'],
            '--hw-btn-primary' => $dark['button_primary'],
            '--hw-btn-primary-hover' => $dark['button_hover'],
            '--hw-btn-success' => $dark['success'],
            '--hw-btn-danger-outline' => $dark['danger'],
            '--hw-input-bg' => '#0F172A',
            '--hw-input-border' => $dark['input_border'],
            '--hw-table-header' => '#152033',
            '--hw-table-alt' => '#121C2E',
            '--hw-table-hover' => '#1E293B',
            '--hw-table-border' => $dark['border'],
            '--hw-nav-top' => $dark['header_bg'],
            '--hw-nav-icon' => $dark['text_secondary'],
            '--hw-status-active' => $dark['success'],
            '--hw-status-potential' => $dark['accent'],
            '--hw-status-inactive' => $dark['warning'],
            '--hw-status-left' => $dark['danger'],
            '--hw-status-neutral' => $dark['link'],
            '--hw-login-bg' => (string) ($dark['background'] ?? '#0B1220'),
            '--hw-login-btn' => (string) ($dark['button_primary'] ?? $login['button_color'] ?? '#5B7AA5'),
            '--hw-login-btn-text' => '#FFFFFF',
            '--hw-login-bg-image' => $this->cssBackgroundImage($login['background_image'] ?? null),
        ], []);

        $css = ":root {\n{$root}\n}\n";

        if ($mode === 'dark') {
            $css .= "html.dark, .dark {\n{$darkBlock}\n}\n";
            $css .= "html { color-scheme: dark; }\n";
            $css .= ":root {\n{$darkBlock}\n}\n";
        } elseif ($mode === 'auto') {
            $css .= "@media (prefers-color-scheme: dark) {\n:root {\n{$darkBlock}\n}\n}\n";
            $css .= "html.dark, .dark {\n{$darkBlock}\n}\n";
        } else {
            $css .= "html.dark, .dark {\n{$darkBlock}\n}\n";
        }

        $css .= ".fi-sidebar { width: var(--hw-sidebar-width) !important; max-width: var(--hw-sidebar-width) !important; }\n";
        $css .= "body, .fi-body { font-family: var(--hw-font-body); font-size: calc(var(--hw-font-size-base) * var(--hw-space-scale)); line-height: var(--hw-line-height); letter-spacing: var(--hw-letter-spacing); }\n";
        $css .= "h1,h2,h3,h4,.fi-header-heading { font-family: var(--hw-font-heading); }\n";
        $css .= ".fi-btn, .hw-btn { border-radius: var(--hw-button-radius) !important; }\n";

        return $css;
    }

    /**
     * @param  array<string, string>  $colors
     * @param  array<string, string>  $extra
     * @param  array<string, mixed>  $nav
     */
    protected function colorBlockToHwVars(array $colors, array $extra = [], array $nav = []): string
    {
        $map = [
            '--hw-sidebar-bg' => $colors['sidebar_bg'] ?? $nav['sidebar_color'] ?? '#1B2942',
            '--hw-sidebar-active' => $colors['sidebar_active'] ?? $nav['active_menu_color'] ?? '#2B4066',
            '--hw-sidebar-text' => '#FFFFFF',
            '--hw-sidebar-text-muted' => $colors['text_secondary'] ?? '#8C9BB5',
            '--hw-canvas-bg' => $colors['background'],
            '--hw-card-bg' => $colors['surface'],
            '--hw-card-border' => $colors['border'],
            '--hw-eyebrow-text' => $colors['text_secondary'],
            '--hw-text' => $colors['text_primary'],
            '--hw-text-muted' => $colors['text_secondary'],
            '--hw-link' => $colors['link'] ?? $colors['primary'] ?? '#2563EB',
            '--hw-chart-label' => $colors['text_secondary'],
            '--hw-chart-grid' => $colors['border'],
            '--hw-chart-tooltip-bg' => $colors['surface'],
            '--hw-chart-tooltip-text' => $colors['text_primary'],
            '--hw-overlay' => 'rgba(15, 23, 42, 0.45)',
            '--hw-btn-navy' => $colors['sidebar_bg'],
            '--hw-chart-1' => $colors['sidebar_bg'],
            '--hw-chart-2' => $colors['link'],
            '--hw-chart-3' => '#14B8A6',
            '--hw-chart-4' => $colors['accent'],
            '--hw-chart-5' => '#EC4899',
            '--hw-chart-6' => $colors['warning'],
            '--hw-chart-7' => $colors['danger'],
            '--hw-chart-8' => $colors['success'],
            '--hw-chart-9' => $colors['secondary'],
        ];

        $map = array_merge($map, $extra);

        $lines = [];
        foreach ($map as $prop => $value) {
            $lines[] = '  '.$prop.': '.$value.';';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    protected function buildPdfMap(array $config): array
    {
        $light = Arr::get($config, 'colors.light', ThemeDefaults::lightColors());
        $identity = Arr::get($config, 'identity', []);
        $logos = Arr::get($config, 'logos', []);
        $buttons = Arr::get($config, 'buttons', []);

        return [
            'primary' => (string) ($light['primary'] ?? ThemeDefaults::lightColors()['primary']),
            'app_name' => (string) ($identity['app_name'] ?? 'Hope Works'),
            'short_name' => (string) ($identity['short_name'] ?? 'Hope Works'),
            'footer_text' => (string) ($identity['footer_text'] ?? ''),
            'copyright_text' => (string) ($identity['copyright_text'] ?? ''),
            'copyright_url' => (string) ($identity['copyright_url'] ?? ''),
            'main_logo' => (string) (BrandingAsset::normalizePath($logos['main_logo'] ?? null) ?? ''),
            'favicon' => (string) (BrandingAsset::normalizePath($logos['favicon'] ?? null) ?? ''),
            'button_primary' => (string) ($light['primary'] ?? $buttons['primary'] ?? ThemeDefaults::lightColors()['primary']),
        ];
    }

    protected function cssBackgroundImage(mixed $path): string
    {
        $url = BrandingAsset::publicUrl($path);

        if ($url === null) {
            return 'none';
        }

        return 'url('.json_encode($url).')';
    }
}
