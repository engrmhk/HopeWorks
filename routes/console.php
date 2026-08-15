<?php

use App\Console\Commands\EvaluateSubscriptionStatusesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Subscription status evaluation schedule
 | ---------------------------------------
 | Command: subscriptions:evaluate-statuses
 | Frequency: every minute
 | Purpose: Drive active → grace → suspended using current_period_end / grace_started_at
 |          without waiting for a church-instance heartbeat.
 |
 | Production requires the OS cron entry:
 |   * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
 |
 | See docs/SUBSCRIPTION_STATUS_EVALUATION.md
 */
Schedule::command(EvaluateSubscriptionStatusesCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->name('subscriptions-evaluate-statuses');
