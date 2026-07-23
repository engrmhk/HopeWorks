<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;

class DunningService
{
    public function __construct(
        protected SubscriptionEnforcementService $subscriptionEnforcementService,
        protected AuditLogService $auditLogService,
    ) {}

    public function recordFailedAttempt(Subscription $subscription, int $attemptCount): Subscription
    {
        $maxAttempts = (int) config('hopeworks.stripe.dunning_max_attempts', 3);

        if ($subscription->dunning_started_at === null) {
            $subscription->dunning_started_at = now();
        }

        $subscription->dunning_attempt_count = $attemptCount;
        $subscription->save();

        if ($attemptCount < $maxAttempts) {
            return $subscription->fresh();
        }

        return $this->exhaustDunning($subscription);
    }

    public function exhaustDunning(Subscription $subscription): Subscription
    {
        if ($subscription->dunning_exhausted_at !== null) {
            return $subscription;
        }

        $subscription->dunning_exhausted_at = now();
        $subscription->save();

        if ($subscription->status === SubscriptionStatus::Active) {
            $this->subscriptionEnforcementService->transition($subscription, SubscriptionStatus::Grace, [
                'grace_started_at' => now(),
            ]);
        }

        $this->auditLogService->log(
            actor: null,
            action: 'subscription.dunning_exhausted',
            subject: $subscription,
            before: null,
            after: [
                'dunning_attempt_count' => $subscription->dunning_attempt_count,
                'grace_started_at' => $subscription->fresh()->grace_started_at?->toIso8601String(),
            ],
            ip: request()?->ip(),
            highVisibility: true,
        );

        return $subscription->fresh();
    }

    public function clearDunning(Subscription $subscription): Subscription
    {
        $subscription->fill([
            'dunning_started_at' => null,
            'dunning_attempt_count' => 0,
            'dunning_exhausted_at' => null,
        ]);
        $subscription->save();

        return $subscription->fresh();
    }
}
