<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Request;

/**
 * Subscription status state machine: active → grace → suspended.
 *
 * Drivers (all call evaluate()):
 * 1. Scheduled job `subscriptions:evaluate-statuses` — every minute (routes/console.php)
 * 2. Church heartbeat POST /api/v1/heartbeat
 * 3. Staff "Force Status Check" on Church edit
 *
 * Core App never pushes; it learns status on the next client-initiated heartbeat
 * (or when its cached license JWT expires). Use invalidateCachedLicense() to
 * revoke outstanding JWTs so the next heartbeat cannot reuse a stale token.
 */
class SubscriptionEnforcementService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function evaluate(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return $this->touchEvaluated($subscription);
        }

        $beforeStatus = $subscription->status;
        $now = now();
        $result = $subscription;

        if ($subscription->status === SubscriptionStatus::Active) {
            if ($subscription->current_period_end && $now->isAfter($subscription->current_period_end)) {
                $result = $this->transition($subscription, SubscriptionStatus::Grace, [
                    'grace_started_at' => $now,
                ]);
            }
        }

        if ($result->status === SubscriptionStatus::Grace) {
            $graceStartedAt = $result->grace_started_at ?? $now;
            $graceEndsAt = $graceStartedAt->copy()->addDays($result->grace_period_days);

            if ($now->isAfter($graceEndsAt)) {
                $result = $this->transition($result, SubscriptionStatus::Suspended);
            }
        }

        $evaluated = $this->touchEvaluated($result->fresh() ?? $result);

        if ($beforeStatus !== $evaluated->status) {
            // transition() already audited the change; touchEvaluated is enough here.
        }

        return $evaluated;
    }

    /**
     * @return array{evaluated: int, transitioned: int}
     */
    public function evaluateDueSubscriptions(?int $churchId = null): array
    {
        $query = Subscription::query()
            ->where('status', '!=', SubscriptionStatus::Cancelled->value)
            ->when($churchId !== null, fn ($q) => $q->where('church_id', $churchId));

        $evaluated = 0;
        $transitioned = 0;

        $query->orderBy('id')->chunkById(100, function ($subscriptions) use (&$evaluated, &$transitioned): void {
            foreach ($subscriptions as $subscription) {
                $before = $subscription->status;
                $after = $this->evaluate($subscription);
                $evaluated++;

                if ($before !== $after->status) {
                    $transitioned++;
                }
            }
        });

        return [
            'evaluated' => $evaluated,
            'transitioned' => $transitioned,
        ];
    }

    public function forceStatusCheckForChurch(
        Church $church,
        Authenticatable|User|null $actor = null,
    ): ?Subscription {
        $subscription = $church->subscription;

        if ($subscription === null) {
            return null;
        }

        $before = $subscription->status;
        $after = $this->evaluate($subscription);

        $this->auditLogService->log(
            actor: $actor,
            action: 'subscription.force_status_check',
            subject: $after,
            before: ['status' => $before->value],
            after: [
                'status' => $after->status->value,
                'status_evaluated_at' => $after->status_evaluated_at?->toIso8601String(),
                'transitioned' => $before !== $after->status,
            ],
            ip: Request::ip(),
            highVisibility: true,
        );

        return $after;
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
        $subscription->status_evaluated_at = now();

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

    protected function touchEvaluated(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['status_evaluated_at' => now()])->saveQuietly();

        return $subscription->fresh();
    }
}
