<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChurchStatus;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\LicenseKeyService;
use App\Services\SubscriptionEnforcementService;
use App\Services\TenantHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function __construct(
        protected LicenseKeyService $licenseKeyService,
        protected SubscriptionEnforcementService $subscriptionEnforcementService,
        protected TenantHealthService $tenantHealthService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Church $church */
        $church = $request->attributes->get('church');

        if ($church->status !== ChurchStatus::Active) {
            return response()->json([
                'message' => 'This church instance is deactivated.',
                'church_status' => $church->status->value,
            ], 403);
        }

        $church->load(['subscription.plan']);

        $subscription = $church->subscription;

        if ($subscription === null) {
            return response()->json([
                'message' => 'No subscription found for this church.',
            ], 422);
        }

        $metrics = $request->validate([
            'storage_bytes' => ['nullable', 'integer', 'min:0'],
            'active_user_count' => ['nullable', 'integer', 'min:0'],
            'last_login_at' => ['nullable', 'date'],
            'enabled_modules' => ['nullable', 'array'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'app_tag' => ['nullable', 'string', 'max:50'],
        ]);

        if ($metrics !== []) {
            $this->tenantHealthService->recordHeartbeatMetrics($church, $metrics);
        }

        $church->forceFill(['last_heartbeat_at' => now()])->saveQuietly();

        $subscription = $this->subscriptionEnforcementService->evaluate($subscription);
        $licenseKey = $this->licenseKeyService->issueForSubscription($subscription);

        return response()->json([
            'status' => $subscription->status->value,
            'enforcement_policy' => $subscription->enforcement_policy->value,
            'license_key' => $licenseKey->signed_jwt,
            // JWT re-sync TTL (default 48h). Do not confuse with billing current_period_end.
            'expires_at' => $licenseKey->expires_at->toIso8601String(),
            'license_expires_at' => $licenseKey->expires_at->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'grace_started_at' => $subscription->grace_started_at?->toIso8601String(),
            'grace_period_days' => $subscription->grace_period_days,
            'church_id' => $church->id,
            'plan_id' => $subscription->plan_id,
            'enabled_modules' => $subscription->plan?->module_eligibility ?? [],
        ]);
    }
}
