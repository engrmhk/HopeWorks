<?php

namespace App\Http\Middleware;

use App\Models\LoginHistory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordLoginHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->is('admin/login') && $request->isMethod('post')) {
            LoginHistory::query()->create([
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
                'device' => $request->userAgent(),
                'success' => $response->isSuccessful() || $response->isRedirection(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
