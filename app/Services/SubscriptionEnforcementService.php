<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Facades\Request;

class SubscriptionEnforcementService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function evaluate(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return $subscription;
        }

        $now = now();

        if ($subscription->status === SubscriptionStatus::Active) {
            if ($subscription->current_period_end && $now->isAfter($subscription->current_period_end)) {
                return $this->transition($subscription, SubscriptionStatus::Grace, [
                    'grace_started_at' => $now,
                ]);
            }
        }

        if ($subscription->status === SubscriptionStatus::Grace) {
            $graceStartedAt = $subscription->grace_started_at ?? $now;
            $graceEndsAt = $graceStartedAt->copy()->addDays($subscription->grace_period_days);

            if ($now->isAfter($graceEndsAt)) {
                return $this->transition($subscription, SubscriptionStatus::Suspended);
            }
        }

        return $subscription->fresh();
    }

    public function transition(Subscription $subscription, SubscriptionStatus $newStatus, array $attributes = []): Subscription
    {
        if ($subscription->status === $newStatus) {
            return $subscription;
        }

        $before = [
            'status' => $subscription->status->value,
            'grace_started_at' => $subscription->grace_started_at?->toIso8601String(),
        ];

        $subscription->status = $newStatus;
        $subscription->fill($attributes);
        $subscription->save();

        $this->auditLogService->log(
            actor: null,
            action: 'subscription.status_changed',
            subject: $subscription,
            before: $before,
            after: [
                'status' => $subscription->status->value,
                'grace_started_at' => $subscription->grace_started_at?->toIso8601String(),
            ],
            ip: Request::ip(),
        );

        return $subscription->fresh();
    }

    public function activate(Subscription $subscription, ?\DateTimeInterface $periodEnd = null): Subscription
    {
        $before = [
            'status' => $subscription->status->value,
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'grace_started_at' => $subscription->grace_started_at?->toIso8601String(),
        ];

        $subscription->status = SubscriptionStatus::Active;
        $subscription->grace_started_at = null;

        if ($periodEnd !== null) {
            $subscription->current_period_end = $periodEnd;
        }

        $subscription->save();

        $this->auditLogService->log(
            actor: null,
            action: 'subscription.activated',
            subject: $subscription,
            before: $before,
            after: [
                'status' => $subscription->status->value,
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'grace_started_at' => null,
            ],
            ip: Request::ip(),
        );

        return $subscription->fresh();
    }
}
