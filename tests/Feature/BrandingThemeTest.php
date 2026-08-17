<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\BrandingSettings;
use App\Models\Theme;
use App\Models\User;
use App\Services\Themes\ThemeCompiler;
use App\Services\Themes\ThemeResolver;
use App\Services\Themes\ThemeService;
use App\Support\BrandingAsset;
use App\Support\StatusColorMap;
use App\Support\ThemeDefaults;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingThemeTest extends TestCase
{
    public function test_normalize_path_unwraps_arrays_and_rejects_invalid(): void
    {
        $this->assertSame('branding/logos/a.png', BrandingAsset::normalizePath(['branding/logos/a.png']));
        $this->assertSame('branding/logos/a.png', BrandingAsset::normalizePath('branding/logos/a.png'));
        $this->assertNull(BrandingAsset::normalizePath(''));
        $this->assertNull(BrandingAsset::normalizePath('Array'));
        $this->assertNull(BrandingAsset::normalizePath(null));
    }

    public function test_migration_seeds_an_active_default_theme(): void
    {
        $theme = Theme::query()->where('is_active', true)->first();

        $this->assertNotNull($theme);
        $this->assertSame(ThemeDefaults::lightColors()['primary'], $theme->mergedConfig()['colors']['light']['primary']);
        $this->assertSame('Welcome back', $theme->mergedConfig()['login_page']['welcome_title']);
    }

    public function test_saving_theme_updates_login_copy_and_primary_color(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $theme = app(ThemeService::class)->ensureActiveTheme($admin);
        $config = ThemeDefaults::all();
        $config['identity']['app_name'] = 'Acme Control';
        $config['colors']['light']['primary'] = '#112233';
        $config['login_page']['welcome_title'] = 'Staff sign in';
        $config['login_page']['welcome_subtitle'] = 'Use your platform credentials';
        $config['login_page']['button_color'] = '#112233';

        app(ThemeService::class)->saveTheme($theme, 'Acme', $config);
        Cache::flush();

        $resolved = app(ThemeResolver::class)->resolvedConfig();
        $this->assertSame('Acme Control', $resolved['identity']['app_name']);
        $this->assertSame('#112233', $resolved['colors']['light']['primary']);
        $this->assertSame('Staff sign in', $resolved['login_page']['welcome_title']);
        $this->assertSame('#112233', StatusColorMap::defaultThemeColor());

        $css = app(ThemeCompiler::class)->compileCss();
        $this->assertStringContainsString('#112233', $css);
        $this->assertStringContainsString('--hw-login-btn', $css);
    }

    public function test_login_page_renders_custom_heading_and_subtitle(): void
    {
        $theme = app(ThemeService::class)->ensureActiveTheme();
        $config = ThemeDefaults::all();
        $config['login_page']['welcome_title'] = 'Control Plane Access';
        $config['login_page']['welcome_subtitle'] = 'Operators only';
        app(ThemeService::class)->saveTheme($theme, 'Control Plane', $config);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Control Plane Access')
            ->assertSee('Operators only');
    }

    public function test_branding_settings_page_is_reachable_and_can_save(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        Livewire::actingAs($admin)
            ->test(BrandingSettings::class)
            ->assertSuccessful()
            ->fillForm([
                'name' => 'Platform look',
                'identity' => [
                    'app_name' => 'Hope Works Ops',
                ],
                'login_page' => [
                    'welcome_title' => 'Hello operators',
                ],
                'colors' => [
                    'light' => [
                        'primary' => '#445566',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $resolved = app(ThemeResolver::class)->resolvedConfig();
        $this->assertSame('Hope Works Ops', $resolved['identity']['app_name']);
        $this->assertSame('Hello operators', $resolved['login_page']['welcome_title']);
        $this->assertSame('#445566', $resolved['colors']['light']['primary']);
    }

    public function test_invalid_color_is_rejected(): void
    {
        $theme = app(ThemeService::class)->ensureActiveTheme();
        $config = ThemeDefaults::all();
        $config['colors']['light']['primary'] = 'not-a-color';

        $this->expectException(ValidationException::class);
        app(ThemeService::class)->saveTheme($theme, 'Bad', $config);
    }

    public function test_reset_restores_hardcoded_defaults(): void
    {
        $theme = app(ThemeService::class)->ensureActiveTheme();
        $config = ThemeDefaults::all();
        $config['login_page']['welcome_title'] = 'Temporary';
        $config['colors']['light']['primary'] = '#000000';
        app(ThemeService::class)->saveTheme($theme, 'Custom', $config);

        app(ThemeService::class)->resetToHardcodedDefaults($theme->fresh());

        $resolved = app(ThemeResolver::class)->resolvedConfig();
        $this->assertSame('Welcome back', $resolved['login_page']['welcome_title']);
        $this->assertSame(ThemeDefaults::lightColors()['primary'], $resolved['colors']['light']['primary']);
    }

    public function test_login_logo_falls_back_to_main_logo_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logos/main.png', 'png');

        $theme = app(ThemeService::class)->ensureActiveTheme();
        $config = ThemeDefaults::all();
        $config['logos']['main_logo'] = 'branding/logos/main.png';
        app(ThemeService::class)->saveTheme($theme, 'With logo', $config);

        $this->assertSame(
            Storage::disk('public')->url('branding/logos/main.png'),
            BrandingAsset::publicUrl($theme->fresh()->mergedConfig()['logos']['main_logo']),
        );

        Livewire::test(Login::class)
            ->assertSuccessful()
            ->assertSee('Welcome back');
    }
}
