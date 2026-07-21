<?php

namespace App\Services\Communications\Providers;

use App\Services\Communications\Contracts\MessageProvider;
use Illuminate\Support\Facades\Log;

class LogMessageProvider implements MessageProvider
{
    public function send(string $to, string $subject, string $body, array $context = []): void
    {
        Log::info('billing.notification', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'context' => $context,
        ]);
    }
}
