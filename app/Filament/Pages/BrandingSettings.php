<?php

namespace App\Filament\Pages;

use App\Models\Theme;
use App\Services\Themes\ThemeCompiler;
use App\Services\Themes\ThemeService;
use App\Support\BrandingAsset;
use App\Support\ThemeDefaults;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class BrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Branding & Theme';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Branding & Theme';

    protected string $view = 'filament.pages.branding-settings';

    public ?array $data = [];

    public ?int $themeId = null;

    public string $previewCss = '';

    public function mount(): void
    {
        $this->loadThemeIntoForm();
    }

    public function updated($name): void
    {
        if (is_string($name) && str_starts_with($name, 'data.')) {
            $this->refreshPreviewCss();
        }
    }

    protected function loadThemeIntoForm(): void
    {
        $theme = app(ThemeService::class)->ensureActiveTheme(auth()->user());
        $this->themeId = $theme->id;
        $this->data = $this->flattenConfig($theme->mergedConfig(), $theme->name);
        $this->form->fill($this->data);
        $this->refreshPreviewCss();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->description('Names shown in the sidebar, login page, and invoices.')
                    ->schema([
                        TextInput::make('name')->label('Theme name')->required()->maxLength(120),
                        TextInput::make('identity.app_name')->label('App name')->maxLength(120)
                            ->helperText('Shown in the sidebar when no logo is uploaded, and on invoices.'),
                        TextInput::make('identity.short_name')->label('Short name')->maxLength(60),
                        Textarea::make('identity.footer_text')->label('Invoice footer text')->rows(2),
                        TextInput::make('identity.copyright_text')->label('Copyright text')->maxLength(255),
                        TextInput::make('identity.copyright_url')->label('Copyright URL')->url()->maxLength(255),
                    ])->columns(2),
                Section::make('Logos')
                    ->description('Upload PNG or SVG. Re-save after upload if the preview looks stale.')
                    ->schema([
                        FileUpload::make('logos.main_logo')
                            ->label('Main logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding/logos')
                            ->visibility('public')
                            ->maxFiles(1)
                            ->helperText('Sidebar and invoices.'),
                        FileUpload::make('logos.login_logo')
                            ->label('Login logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding/logos')
                            ->visibility('public')
                            ->maxFiles(1)
                            ->helperText('Falls back to the main logo if empty.'),
                        FileUpload::make('logos.favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('branding/favicons')
                            ->visibility('public')
                            ->maxFiles(1),
                        FileUpload::make('login_page.background_image')
                            ->label('Login background')
                            ->image()
                            ->disk('public')
                            ->directory('branding/login')
                            ->visibility('public')
                            ->maxFiles(1),
                    ])->columns(2),
                Section::make('Login page')
                    ->description('Heading, subtitle, and colors for /admin/login.')
                    ->schema([
                        TextInput::make('login_page.welcome_title')->label('Welcome title')->maxLength(120),
                        TextInput::make('login_page.welcome_subtitle')->label('Welcome subtitle')->maxLength(255),
                        ColorPicker::make('login_page.background_color')->label('Background color'),
                        ColorPicker::make('login_page.button_color')->label('Button color'),
                        ColorPicker::make('login_page.button_text_color')->label('Button text'),
                    ])->columns(2),
                Section::make('Light colors')->schema([
                    ColorPicker::make('colors.light.primary')->label('Primary')->live(onBlur: true),
                    ColorPicker::make('colors.light.accent')->label('Accent')->live(onBlur: true),
                    ColorPicker::make('colors.light.success')->label('Success')->live(onBlur: true),
                    ColorPicker::make('colors.light.warning')->label('Warning')->live(onBlur: true),
                    ColorPicker::make('colors.light.danger')->label('Danger')->live(onBlur: true),
                    ColorPicker::make('colors.light.background')->label('Canvas')->live(onBlur: true),
                    ColorPicker::make('colors.light.surface')->label('Card / surface')->live(onBlur: true),
                    ColorPicker::make('colors.light.sidebar_bg')->label('Sidebar')->live(onBlur: true),
                    ColorPicker::make('colors.light.sidebar_active')->label('Sidebar active')->live(onBlur: true),
                    ColorPicker::make('colors.light.text_primary')->label('Text')->live(onBlur: true),
                    ColorPicker::make('colors.light.border')->label('Border')->live(onBlur: true),
                    ColorPicker::make('colors.light.button_primary')->label('Button primary')->live(onBlur: true),
                ])->columns(3),
                Section::make('Dark colors')->schema([
                    ColorPicker::make('colors.dark.primary')->label('Primary')->live(onBlur: true),
                    ColorPicker::make('colors.dark.accent')->label('Accent')->live(onBlur: true),
                    ColorPicker::make('colors.dark.success')->label('Success')->live(onBlur: true),
                    ColorPicker::make('colors.dark.warning')->label('Warning')->live(onBlur: true),
                    ColorPicker::make('colors.dark.danger')->label('Danger')->live(onBlur: true),
                    ColorPicker::make('colors.dark.background')->label('Canvas')->live(onBlur: true),
                    ColorPicker::make('colors.dark.surface')->label('Card / surface')->live(onBlur: true),
                    ColorPicker::make('colors.dark.sidebar_bg')->label('Sidebar')->live(onBlur: true),
                    ColorPicker::make('colors.dark.sidebar_active')->label('Sidebar active')->live(onBlur: true),
                    ColorPicker::make('colors.dark.text_primary')->label('Text')->live(onBlur: true),
                    ColorPicker::make('colors.dark.border')->label('Border')->live(onBlur: true),
                    ColorPicker::make('colors.dark.button_primary')->label('Button primary')->live(onBlur: true),
                ])->columns(3)->collapsed(),
                Section::make('Typography & layout')->schema([
                    TextInput::make('typography.heading_font')->label('Heading font'),
                    TextInput::make('typography.body_font')->label('Body font'),
                    TextInput::make('typography.base_font_size')->numeric()->label('Base font size (px)'),
                    Select::make('layout.mode')->label('Color mode')->options([
                        'light' => 'Light',
                        'dark' => 'Dark',
                        'auto' => 'Auto (OS preference)',
                    ])->live(),
                    Select::make('layout.card_shadow')->label('Card shadow')->options([
                        'none' => 'None',
                        'soft' => 'Soft',
                        'medium' => 'Medium',
                        'strong' => 'Strong',
                    ])->live(),
                    Select::make('layout.button_style')->label('Button style')->options([
                        'rounded' => 'Rounded',
                        'pill' => 'Pill',
                        'square' => 'Square',
                    ])->live(),
                    Select::make('layout.spacing')->label('Spacing')->options([
                        'compact' => 'Compact',
                        'comfortable' => 'Comfortable',
                    ])->live(),
                    TextInput::make('layout.border_radius')->numeric()->label('Border radius (px)')->live(onBlur: true),
                    TextInput::make('layout.sidebar_width')->numeric()->label('Sidebar width (px)'),
                ])->columns(2)->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $theme = Theme::query()->findOrFail($this->themeId);
        $state = $this->form->getState();
        $config = $this->expandConfig($state);
        $name = (string) ($state['name'] ?? $theme->name);

        app(ThemeService::class)->saveTheme($theme, $name, $config, activate: true);

        Notification::make()->title('Theme saved')->success()->send();
        $this->loadThemeIntoForm();
    }

    public function resetTheme(): void
    {
        $theme = Theme::query()->findOrFail($this->themeId);
        app(ThemeService::class)->resetToHardcodedDefaults($theme);
        Notification::make()->title('Reset to platform defaults')->success()->send();
        $this->loadThemeIntoForm();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Reset to Default')
                ->color('warning')
                ->requiresConfirmation()
                ->action('resetTheme'),
            Action::make('save')
                ->label('Save Theme')
                ->action('save'),
        ];
    }

    protected function refreshPreviewCss(): void
    {
        $this->previewCss = app(ThemeCompiler::class)->compileCss($this->expandConfig($this->data ?? []));
    }

    /** @param  array<string, mixed>  $config */
    protected function flattenConfig(array $config, string $name): array
    {
        return [
            'name' => $name,
            'colors' => $config['colors'] ?? ThemeDefaults::all()['colors'],
            'typography' => $config['typography'] ?? ThemeDefaults::all()['typography'],
            'layout' => $config['layout'] ?? ThemeDefaults::all()['layout'],
            'identity' => $config['identity'] ?? ThemeDefaults::all()['identity'],
            'logos' => $config['logos'] ?? ThemeDefaults::all()['logos'],
            'login_page' => $config['login_page'] ?? ThemeDefaults::all()['login_page'],
            'navigation' => $config['navigation'] ?? ThemeDefaults::all()['navigation'],
            'tables' => $config['tables'] ?? ThemeDefaults::all()['tables'],
            'forms' => $config['forms'] ?? ThemeDefaults::all()['forms'],
            'buttons' => $config['buttons'] ?? ThemeDefaults::all()['buttons'],
        ];
    }

    /** @param  array<string, mixed>  $state @return array<string, mixed> */
    protected function expandConfig(array $state): array
    {
        $config = ThemeDefaults::all();

        foreach (['colors', 'typography', 'layout', 'identity', 'logos', 'login_page', 'navigation', 'tables', 'forms', 'buttons'] as $section) {
            if (isset($state[$section]) && is_array($state[$section])) {
                $config[$section] = array_replace_recursive($config[$section], $state[$section]);
            }
        }

        foreach (['main_logo', 'login_logo', 'favicon'] as $logoKey) {
            $config['logos'][$logoKey] = BrandingAsset::normalizePath($config['logos'][$logoKey] ?? null);
        }
        $config['login_page']['background_image'] = BrandingAsset::normalizePath($config['login_page']['background_image'] ?? null);
        $config['typography']['custom_font_path'] = BrandingAsset::normalizePath($config['typography']['custom_font_path'] ?? null);

        $primary = Arr::get($config, 'colors.light.primary');
        if ($primary) {
            $config['colors']['light']['button_primary'] = $primary;
            $config['buttons']['primary'] = $primary;
            $config['forms']['focus_color'] = $primary;
            $config['login_page']['button_color'] = Arr::get($config, 'login_page.button_color') ?: $primary;
        }

        $lightSidebar = Arr::get($config, 'colors.light.sidebar_bg');
        $lightSidebarActive = Arr::get($config, 'colors.light.sidebar_active');
        if ($lightSidebar) {
            $config['navigation']['sidebar_color'] = $lightSidebar;
        }
        if ($lightSidebarActive) {
            $config['navigation']['active_menu_color'] = $lightSidebarActive;
            $config['navigation']['menu_hover_color'] = $lightSidebarActive;
        }

        return $config;
    }
}
