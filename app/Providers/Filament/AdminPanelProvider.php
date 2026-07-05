<?php

namespace App\Providers\Filament;

use App\Filament\Pages\BrandingSettings;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\SynodDashboard;
use App\Filament\Widgets\AttendanceOverviewWidget;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\ForcePasswordReset;
use App\Models\ChurchBranding;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration(false)
            ->colors(function (): array {
                $themeColor = ChurchBranding::query()
                    ->where('church_id', auth()->user()?->church_id)
                    ->value('theme_color');

                return [
                    'primary' => $themeColor ? Color::hex($themeColor) : Color::Amber,
                ];
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverResources(in: app_path('Modules'), for: 'App\Modules')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                Onboarding::class,
                BrandingSettings::class,
                SynodDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                AttendanceOverviewWidget::class,
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
                ForcePasswordReset::class,
                EnsureOnboardingComplete::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.hooks.subscription-banner')->render(),
            );
    }
}
