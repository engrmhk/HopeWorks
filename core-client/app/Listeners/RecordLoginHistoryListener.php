<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordLoginHistoryListener
{
    public function __construct(
        protected Request $request,
    ) {}

    public function handleLogin(Login $event): void
    {
        LoginHistory::query()->create([
            'user_id' => $event->user->getAuthIdentifier(),
            'ip_address' => $this->request->ip(),
            'device' => $this->request->userAgent(),
            'success' => true,
            'created_at' => now(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        LoginHistory::query()->create([
            'user_id' => null,
            'ip_address' => $this->request->ip(),
            'device' => $this->request->userAgent(),
            'success' => false,
            'created_at' => now(),
        ]);
    }
}
