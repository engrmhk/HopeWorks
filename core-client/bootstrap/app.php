<?php

use App\Http\Middleware\EnforceSubscriptionStatus;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\ForcePasswordReset;
use App\Http\Middleware\RecordLoginHistory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module.enabled' => EnsureModuleEnabled::class,
            'subscription.enforce' => EnforceSubscriptionStatus::class,
            'password.force_reset' => ForcePasswordReset::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            'login.history' => RecordLoginHistory::class,
        ]);

        $middleware->web(append: [
            EnforceSubscriptionStatus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
