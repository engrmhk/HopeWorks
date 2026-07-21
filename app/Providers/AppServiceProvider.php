<?php

namespace App\Providers;

use App\Services\AffiliationService;
use App\Services\AuditLogService;
use App\Services\ChurchInstanceAccessService;
use App\Services\LicenseKeyService;
use App\Services\SubscriptionEnforcementService;
use App\Services\TenantMigrationService;
use App\Services\TenantProvisioningService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(LicenseKeyService::class);
        $this->app->singleton(ChurchInstanceAccessService::class);
        $this->app->singleton(SubscriptionEnforcementService::class);
        $this->app->singleton(AffiliationService::class);
        $this->app->singleton(TenantMigrationService::class);
        $this->app->singleton(TenantProvisioningService::class);
    }

    public function boot(): void
    {
        //
    }
}
