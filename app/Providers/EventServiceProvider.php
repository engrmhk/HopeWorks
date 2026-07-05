<?php

namespace App\Providers;

use App\Listeners\RecordLoginHistoryListener;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            [RecordLoginHistoryListener::class, 'handleLogin'],
        ],
        Failed::class => [
            [RecordLoginHistoryListener::class, 'handleFailed'],
        ],
    ];
}
