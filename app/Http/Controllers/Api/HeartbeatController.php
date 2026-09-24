<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChurchStatus;
use App\Exceptions\LicenseJwtSecretTooShortException;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\LicenseKey;
use App\Models\Synod;
use App\Services\LicenseKeyService;
use App\Services\SubscriptionEnforcementService;
use App\Services\SynodEnforcementService;
use App\Services\TenantHealthService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HeartbeatController extends Controller
{
    public function __construct(
        protected LicenseKeyService $licenseKeyService,
        protected SubscriptionEnforcementService $subscriptionEnforcementService,
        protected TenantHealthService $tenantHealthService,
        protected SynodEnforcementService $synodEnforcementService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $synod = $request->attributes->get('synod');

        if ($synod instanceof Synod) {
            return $this->synodHeartbeat($synod);
        }

        /** @var Church $church */
        $church = $request->attributes->get('church');

        if ($church->status !== ChurchStatus::Active) {
            return response()->json([
                'message' => 'This church instance is deactivated.',
                'church_status' => $church->status->value,
            ], 403);
        }

        $church->load(['subscription.plan', 'synod']);

        $subscription = $church->subscription;
        $isSynodHost = (bool) $church->is_synod_host;

        if ($subscription === null && ! $isSynodHost) {
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

        if ($subscription !== null) {
            $subscription = $this->subscriptionEnforcementService->evaluate($subscription);
        }

        $overlay = $this->synodEnforcementService->overlay(
            $church->synod,
            $subscription?->enforcement_policy,
        );

        try {
            $licenseKey = $subscription !== null
                ? $this->licenseKeyService->issueForSubscription($subscription, $overlay)
                : $this->licenseKeyService->issueComplimentaryForChurch($church, $overlay);
        } catch (LicenseJwtSecretTooShortException|DomainException $e) {
            Log::error('heartbeat.license_jwt_secret', [
                'church_id' => $church->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => (new LicenseJwtSecretTooShortException)->getMessage(),
                'code' => 'license_jwt_secret_too_short',
            ], 503);
        }

        return $this->licenseResponse($licenseKey, $overlay, [
            'status' => $subscription?->status->value ?? 'active',
            'expires_at' => $licenseKey->expires_at->toIso8601String(),
            'license_expires_at' => $licenseKey->expires_at->toIso8601String(),
            'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
            'grace_started_at' => $subscription?->grace_started_at?->toIso8601String(),
            'grace_period_days' => $subscription?->grace_period_days,
            'church_id' => $church->id,
            'plan_id' => $subscription?->plan_id,
            'enabled_modules' => $subscription?->plan?->module_eligibility ?? [],
        ]);
    }

    protected function synodHeartbeat(Synod $synod): JsonResponse
    {
        $synod->forceFill(['last_heartbeat_at' => now()])->saveQuietly();

        $overlay = $this->synodEnforcementService->overlay($synod, $synod->enforcement_policy);

        try {
            $licenseKey = $this->licenseKeyService->issueForSynod($synod, $overlay);
        } catch (LicenseJwtSecretTooShortException|DomainException $e) {
            Log::error('heartbeat.license_jwt_secret', [
                'synod_id' => $synod->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => (new LicenseJwtSecretTooShortException)->getMessage(),
                'code' => 'license_jwt_secret_too_short',
            ], 503);
        }

        return $this->licenseResponse($licenseKey, $overlay, [
            'status' => 'active',
            'expires_at' => $licenseKey->expires_at->toIso8601String(),
            'license_expires_at' => $licenseKey->expires_at->toIso8601String(),
            'current_period_end' => null,
            'grace_started_at' => null,
            'grace_period_days' => null,
            'church_id' => null,
            'plan_id' => null,
            'enabled_modules' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overlay
     * @param  array<string, mixed>  $body
     */
    protected function licenseResponse(LicenseKey $licenseKey, array $overlay, array $body): JsonResponse
    {
        return response()->json([
            ...$body,
            'enforcement_policy' => $overlay['enforcement_policy'],
            'synod_id' => $overlay['synod_id'],
            'synod_status' => $overlay['synod_status'],
            'platform_notices' => $overlay['platform_notices'],
            'license_key' => $licenseKey->signed_jwt,
        ]);
    }
}
