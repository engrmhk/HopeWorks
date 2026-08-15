<?php

namespace App\Console\Commands;

use App\Services\SubscriptionEnforcementService;
use Illuminate\Console\Command;

/**
 * Scheduled status machine for subscriptions (active → grace → suspended).
 *
 * Frequency: every minute (see routes/console.php Schedule registration).
 * Requires `* * * * * php artisan schedule:run` (or Sail/Forge scheduler) in production.
 *
 * Before this command existed, transitions only ran when a church instance
 * called POST /api/v1/heartbeat — editing current_period_end in admin would
 * not change status until the next client poll.
 */
class EvaluateSubscriptionStatusesCommand extends Command
{
    protected $signature = 'subscriptions:evaluate-statuses
                            {--church= : Limit evaluation to a single church id}';

    protected $description = 'Evaluate subscription status transitions (active→grace→suspended) against current time';

    public function handle(SubscriptionEnforcementService $enforcementService): int
    {
        $churchId = $this->option('church');

        $summary = $enforcementService->evaluateDueSubscriptions(
            churchId: $churchId !== null ? (int) $churchId : null,
        );

        $this->info(sprintf(
            'Evaluated %d subscription(s); %d transitioned.',
            $summary['evaluated'],
            $summary['transitioned'],
        ));

        return self::SUCCESS;
    }
}
