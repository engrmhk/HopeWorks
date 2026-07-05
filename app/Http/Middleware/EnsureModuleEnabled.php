<?php

namespace App\Http\Middleware;

use App\Services\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        protected ModuleRegistry $moduleRegistry,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        if (! $this->moduleRegistry->isEnabled($moduleKey)) {
            abort(404);
        }

        return $next($request);
    }
}
