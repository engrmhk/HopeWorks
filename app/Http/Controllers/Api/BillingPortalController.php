<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Church-instance billing portal link.
 *
 * Contract for HopeWorks-church (BL1 suspended-page deep link):
 *   POST /api/v1/billing/portal
 *   Authorization: Bearer {church instance API key}
 *   Optional JSON body: { "return_url": "https://church.example/billing" }
 *   200: { "url": "https://billing.stripe.com/p/session/..." }
 *   401: invalid/missing API key
 *   422: church has no Stripe customer yet / LBP currency / Stripe misconfigured
 */
class BillingPortalController extends Controller
{
    public function __construct(
        protected StripeBillingService $stripeBillingService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Church $church */
        $church = $request->attributes->get('church');

        $data = $request->validate([
            'return_url' => ['nullable', 'url'],
        ]);

        try {
            $result = $this->stripeBillingService->createBillingPortalSession(
                $church,
                $data['return_url'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Unable to create billing portal session.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($result);
    }
}
