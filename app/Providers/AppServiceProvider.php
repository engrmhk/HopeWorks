<?php

namespace App\Providers;

use App\Services\AffiliationService;
use App\Services\AuditLogService;
use App\Services\ChurchInstanceAccessService;
use App\Services\LicenseKeyService;
use App\Services\SubscriptionEnforcementService;
use App\Services\TenantMigrationService;
use App\Services\TenantProvisioningService;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        $this->app->singleton(\App\Services\StripeBillingService::class);
        $this->app->singleton(\App\Services\BillingNotificationService::class);
        $this->app->singleton(\App\Services\Communications\MessageProviderManager::class);
        $this->app->singleton(\App\Services\ManualPaymentService::class);
        $this->app->singleton(\App\Services\DunningService::class);
    }

    public function boot(): void
    {
        Gate::define(User::PERMISSION_RECORD_MANUAL_PAYMENT, function (User $user): bool {
            return $user->hasPermission(User::PERMISSION_RECORD_MANUAL_PAYMENT);
        });
    }
}
