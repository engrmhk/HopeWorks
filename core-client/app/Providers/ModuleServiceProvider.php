<?php

namespace App\Providers;

use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(): void
    {
        $this->registerModuleProviders();
        $this->registerModuleRoutes();

        $this->app->booted(function (): void {
            if (\Illuminate\Support\Facades\Schema::hasTable('modules')) {
                app(ModuleRegistry::class)->syncManifestsToDatabase();
            }
        });
    }

    protected function registerModuleProviders(): void
    {
        $modulesPath = app_path('Modules');

        if (! File::isDirectory($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $directory) {
            $providerClass = $this->resolveModuleProviderClass($directory);

            if ($providerClass && class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    protected function registerModuleRoutes(): void
    {
        $modulesPath = app_path('Modules');

        if (! File::isDirectory($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $directory) {
            $routesFile = $directory.'/routes/web.php';

            if (File::exists($routesFile)) {
                $this->loadRoutesFrom($routesFile);
            }
        }
    }

    protected function resolveModuleProviderClass(string $directory): ?string
    {
        $moduleName = basename($directory);

        return "App\\Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
    }
}
