<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\LicenseKeyService;
use App\Services\SubscriptionEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function __construct(
        protected LicenseKeyService $licenseKeyService,
        protected SubscriptionEnforcementService $subscriptionEnforcementService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Church $church */
        $church = $request->attributes->get('church');
        $church->load(['subscription.plan']);

        $subscription = $church->subscription;

        if ($subscription === null) {
            return response()->json([
                'message' => 'No subscription found for this church.',
            ], 422);
        }

        $subscription = $this->subscriptionEnforcementService->evaluate($subscription);
        $licenseKey = $this->licenseKeyService->issueForSubscription($subscription);

        return response()->json([
            'status' => $subscription->status->value,
            'enforcement_policy' => $subscription->enforcement_policy->value,
            'license_key' => $licenseKey->signed_jwt,
            'expires_at' => $licenseKey->expires_at->toIso8601String(),
            'church_id' => $church->id,
            'plan_id' => $subscription->plan_id,
        ]);
    }
}
