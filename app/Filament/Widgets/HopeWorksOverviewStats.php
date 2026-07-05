<?php

namespace App\Filament\Widgets;

use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Subscription;
use App\Models\Synod;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HopeWorksOverviewStats extends StatsOverviewWidget
{
    protected ?string $heading = 'HopeWorks Control Plane';

    protected function getStats(): array
    {
        $activeCount = Subscription::query()->where('status', SubscriptionStatus::Active)->count();
        $graceCount = Subscription::query()->where('status', SubscriptionStatus::Grace)->count();
        $suspendedCount = Subscription::query()->where('status', SubscriptionStatus::Suspended)->count();

        $mrr = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('plan')
            ->get()
            ->sum(fn (Subscription $subscription): float => (float) ($subscription->plan?->price ?? 0));

        return [
            Stat::make('Total Churches', Church::count())
                ->description('Registered tenant churches')
                ->icon('heroicon-o-building-library'),
            Stat::make('Total Synods', Synod::count())
                ->description('Regional synod organizations')
                ->icon('heroicon-o-user-group'),
            Stat::make('Active Subscriptions', $activeCount)
                ->description('Currently active')
                ->color('success'),
            Stat::make('Grace Period', $graceCount)
                ->description('Past due, in grace')
                ->color('warning'),
            Stat::make('Suspended', $suspendedCount)
                ->description('Enforcement suspended')
                ->color('danger'),
            Stat::make('MRR', '$'.number_format($mrr, 2))
                ->description('Monthly recurring revenue')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
