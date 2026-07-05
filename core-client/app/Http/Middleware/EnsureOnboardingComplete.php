<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->needsOnboarding() && ! $request->routeIs('filament.admin.pages.onboarding', 'filament.admin.auth.logout')) {
            return redirect()->route('filament.admin.pages.onboarding');
        }

        return $next($request);
    }
}
