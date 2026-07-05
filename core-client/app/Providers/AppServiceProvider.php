<?php

namespace App\Providers;

use App\Models\Church;
use App\Models\Person;
use App\Policies\ChurchPolicy;
use App\Policies\PersonPolicy;
use App\Services\LicenseService;
use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LicenseService::class);

        if (class_exists(\App\Custom\CustomServiceProvider::class)) {
            $this->app->register(\App\Custom\CustomServiceProvider::class);
        }
    }

    public function boot(): void
    {
        Gate::policy(Church::class, ChurchPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);

        Blade::if('module', function (string $moduleKey): bool {
            return app(ModuleRegistry::class)->isEnabled($moduleKey);
        });
    }
}
