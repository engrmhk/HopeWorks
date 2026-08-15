@php
    use App\Enums\EnforcementPolicy;
    use App\Enums\SubscriptionStatus;
    use App\Filament\Resources\Churches\Schemas\ChurchForm;

    /** @var \App\Models\Church|null $church */
    /** @var \App\Models\Subscription|null $subscription */
    /** @var SubscriptionStatus|null $status */
    /** @var EnforcementPolicy|null $policy */

    $statusLabel = $status?->label() ?? 'No subscription';
    $statusColor = ChurchForm::statusBadgeColor($status);
    $policyLabel = $policy?->label() ?? '—';
    $policyColor = ChurchForm::policyBadgeColor($policy);
    $periodEnd = $subscription?->current_period_end;
    $evaluatedAt = $subscription?->status_evaluated_at;
    $heartbeatAt = $church?->last_heartbeat_at;

    $toneClasses = match ($status) {
        SubscriptionStatus::Active => 'border-success-500/40 bg-success-50 dark:bg-success-950/30',
        SubscriptionStatus::Grace => 'border-warning-500/40 bg-warning-50 dark:bg-warning-950/30',
        SubscriptionStatus::Suspended => 'border-danger-500/40 bg-danger-50 dark:bg-danger-950/30',
        default => 'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5',
    };
@endphp

<div class="space-y-4">
    <div @class([
        'rounded-xl border-l-4 p-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10',
        $toneClasses,
    ])>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Current status
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="$statusColor" size="lg">
                        {{ $statusLabel }}
                    </x-filament::badge>
                    @if ($policy)
                        <x-filament::badge :color="$policyColor">
                            {{ $policyLabel }}
                        </x-filament::badge>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Period ends
                </p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">
                    {{ $periodEnd ? $periodEnd->timezone(config('app.timezone'))->format('M j, Y') : '—' }}
                </p>
                @if ($periodEnd)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $periodEnd->timezone(config('app.timezone'))->format('g:i A') }}
                        · {{ $periodEnd->diffForHumans() }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-lg bg-white/80 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <x-filament::icon
                    icon="heroicon-m-cpu-chip"
                    class="h-4 w-4"
                />
                Last status evaluation
            </div>
            <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">
                {{ $evaluatedAt?->toDateTimeString() ?? 'Never' }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Scheduler, heartbeat, or Force Status Check
            </p>
        </div>

        <div class="rounded-lg bg-white/80 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <x-filament::icon
                    icon="heroicon-m-signal"
                    class="h-4 w-4"
                />
                Last successful heartbeat
            </div>
            <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">
                {{ $heartbeatAt?->toDateTimeString() ?? 'Never' }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Client pull to POST /api/v1/heartbeat
            </p>
        </div>
    </div>

    @unless ($subscription)
        <p class="text-sm text-gray-600 dark:text-gray-300">
            No subscription yet — create one under Billing, then use <strong>Force Status Check</strong> if you need an immediate evaluation.
        </p>
    @endunless
</div>
