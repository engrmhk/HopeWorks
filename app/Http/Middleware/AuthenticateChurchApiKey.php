<?php

namespace App\Http\Middleware;

use App\Services\LicenseKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateChurchApiKey
{
    public function __construct(
        protected LicenseKeyService $licenseKeyService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $church = $this->licenseKeyService->validateApiKey($token);

        if ($church === null) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $request->attributes->set('church', $church);

        return $next($request);
    }
}
