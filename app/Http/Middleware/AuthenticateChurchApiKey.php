<?php

namespace App\Http\Middleware;

use App\Enums\ChurchStatus;
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

        if ($church !== null) {
            if ($church->status === ChurchStatus::Inactive) {
                return response()->json([
                    'message' => 'This church instance is deactivated.',
                    'church_status' => $church->status->value,
                ], 403);
            }

            $request->attributes->set('church', $church);

            return $next($request);
        }

        $synod = $this->licenseKeyService->validateSynodApiKey($token);

        if ($synod !== null) {
            $request->attributes->set('synod', $synod);

            return $next($request);
        }

        return response()->json(['message' => 'Invalid API key.'], 401);
    }
}
