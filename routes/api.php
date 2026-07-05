<?php

use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/heartbeat', HeartbeatController::class)
        ->middleware('church.api');

    Route::post('/webhooks/stripe', StripeWebhookController::class);
});
