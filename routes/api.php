<?php

use App\Http\Controllers\Api\BillingPortalController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('church.api')->group(function (): void {
        Route::post('/heartbeat', HeartbeatController::class);

        /*
         | Billing portal link for HopeWorks-church (BL1):
         | POST /api/v1/billing/portal
         | Auth: Bearer church instance API key
         | Body (optional): { "return_url": "https://..." }
         | 200: { "url": "https://billing.stripe.com/..." }
         */
        Route::post('/billing/portal', BillingPortalController::class);

        /*
         | Support tickets for HopeWorks-church (SP1 / P7-3).
         | See SupportTicketController docblock for full contract.
         */
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);
        Route::post('/support-tickets', [SupportTicketController::class, 'store']);
        Route::get('/support-tickets/{supportTicket}', [SupportTicketController::class, 'show']);
        Route::post('/support-tickets/{supportTicket}/messages', [SupportTicketController::class, 'storeMessage']);
    });

    Route::post('/webhooks/stripe', StripeWebhookController::class);
});
