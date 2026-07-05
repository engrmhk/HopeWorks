<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnforceSubscriptionStatus
{
    public function __construct(
        protected LicenseService $licenseService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('license_cache')) {
            return $next($request);
        }

        $license = $this->licenseService->current();
        $status = $license->status;
        $policy = $license->enforcement_policy;

        if ($status === 'active') {
            return $next($request);
        }

        if ($status === 'grace') {
            view()->share('subscriptionBanner', [
                'type' => 'grace',
                'message' => 'Your subscription is in a grace period.',
                'expires_at' => $license->expires_at,
            ]);

            return $next($request);
        }

        if ($status === 'suspended') {
            if ($policy === 'read_only' && ! $request->isMethodSafe()) {
                abort(403, 'Account is in read-only mode due to subscription status.');
            }

            if ($policy === 'full_lock' && ! $request->routeIs('billing.suspended')) {
                return redirect()->route('billing.suspended');
            }

            if ($policy === 'banner_only') {
                view()->share('subscriptionBanner', [
                    'type' => 'suspended',
                    'message' => 'Your subscription is suspended.',
                ]);
            }

            if ($policy === 'read_only') {
                view()->share('subscriptionBanner', [
                    'type' => 'read_only',
                    'message' => 'Your account is in read-only mode.',
                ]);
            }
        }

        return $next($request);
    }
}
