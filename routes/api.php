<?php

use App\Http\Controllers\Api\BillingPortalController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/heartbeat', HeartbeatController::class)
        ->middleware('church.api');

    /*
     | Billing portal link for HopeWorks-church (BL1):
     | POST /api/v1/billing/portal
     | Auth: Bearer church instance API key
     | Body (optional): { "return_url": "https://..." }
     | 200: { "url": "https://billing.stripe.com/..." }
     */
    Route::post('/billing/portal', BillingPortalController::class)
        ->middleware('church.api');

    Route::post('/webhooks/stripe', StripeWebhookController::class);
});
