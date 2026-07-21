<?php

namespace App\Services\Communications;

use App\Services\Communications\Contracts\MessageProvider;
use App\Services\Communications\Providers\LogMessageProvider;
use App\Services\Communications\Providers\MailMessageProvider;

class MessageProviderManager
{
    public function driver(?string $name = null): MessageProvider
    {
        $name ??= config('hopeworks.messaging.driver', 'log');

        return match ($name) {
            'mail' => app(MailMessageProvider::class),
            'log' => app(LogMessageProvider::class),
            default => app(LogMessageProvider::class),
        };
    }
}
