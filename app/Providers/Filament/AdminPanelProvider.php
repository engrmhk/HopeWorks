<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\BrandingSettings;
use App\Filament\Pages\TenantHealthDashboard;
use App\Services\Themes\ThemeCompiler;
use App\Services\Themes\ThemeResolver;
use App\Support\BrandingAsset;
use App\Support\StatusColorMap;
use App\Support\ThemeDefaults;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName(fn (): string => $this->resolveBrandName())
            ->brandLogo(fn (): string|HtmlString => $this->resolveBrandLogo())
            ->brandLogoHeight('2.85rem')
            ->favicon(fn (): ?string => $this->resolveFavicon())
            ->colors(function (): array {
                $status = StatusColorMap::resolvedStatusColors();

                return [
                    'primary' => Color::hex($status['primary']),
                    'success' => Color::hex($status['success']),
                    'warning' => Color::hex($status['warning']),
                    'danger' => Color::hex($status['danger']),
                    'info' => Color::hex($status['neutral']),
                ];
            })
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => view('filament.hooks.design-tokens')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                TenantHealthDashboard::class,
                BrandingSettings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\HopeWorksOverviewStats::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    protected function resolveBrandName(): string
    {
        try {
            $name = app(ThemeResolver::class)->resolvedConfig()['identity']['app_name'] ?? null;
        } catch (\Throwable) {
            $name = null;
        }

        return filled($name) ? (string) $name : 'Hope Works';
    }

    protected function resolveBrandLogo(): string|HtmlString
    {
        $config = ThemeDefaults::all();

        try {
            $config = app(ThemeResolver::class)->resolvedConfig();
        } catch (\Throwable) {
            // Fall through to the default mark.
        }

        $isLogin = request()->routeIs('filament.admin.auth.login');
        $logoPath = $isLogin
            ? ($config['logos']['login_logo'] ?? $config['logos']['main_logo'] ?? null)
            : ($config['logos']['main_logo'] ?? null);

        $url = BrandingAsset::publicUrl($logoPath);

        if ($url !== null) {
            return $url;
        }

        return new HtmlString(view('filament.hooks.brand-logo')->render());
    }

    protected function resolveFavicon(): ?string
    {
        try {
            $path = app(ThemeCompiler::class)->resolveForPdf()['favicon'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        return BrandingAsset::publicUrl($path);
    }
}
