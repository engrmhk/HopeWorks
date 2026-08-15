<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ClientHealthSnapshot;
use App\Models\Plan;

class TenantHealthService
{
    public function recordHeartbeatMetrics(Church $church, array $metrics): ClientHealthSnapshot
    {
        return ClientHealthSnapshot::create([
            'church_id' => $church->id,
            'storage_bytes' => (int) ($metrics['storage_bytes'] ?? 0),
            'active_user_count' => (int) ($metrics['active_user_count'] ?? 0),
            'last_login_at' => isset($metrics['last_login_at']) ? $metrics['last_login_at'] : null,
            'enabled_modules' => $metrics['enabled_modules'] ?? [],
            'app_version' => $metrics['app_version'] ?? null,
            'app_tag' => $metrics['app_tag'] ?? null,
        ]);
    }

    public function isAtRisk(?ClientHealthSnapshot $snapshot): bool
    {
        if ($snapshot === null) {
            return true;
        }

        $thresholdDays = (int) config('hopeworks.tenant_health.at_risk_no_login_days', 30);

        if ($snapshot->last_login_at === null) {
            return true;
        }

        return $snapshot->last_login_at->lt(now()->subDays($thresholdDays));
    }

    public function isApproachingStorageLimit(Church $church, ?ClientHealthSnapshot $snapshot): bool
    {
        $plan = $church->subscription?->plan;

        if ($plan === null || $snapshot === null) {
            return false;
        }

        $limitBytes = $plan->storage_limit_gb * 1024 * 1024 * 1024;

        return $limitBytes > 0 && $snapshot->storage_bytes >= ($limitBytes * 0.85);
    }

    public function isApproachingUserLimit(Church $church, ?ClientHealthSnapshot $snapshot): bool
    {
        $plan = $church->subscription?->plan;

        if ($plan === null || $snapshot === null) {
            return false;
        }

        return $plan->user_limit > 0 && $snapshot->active_user_count >= (int) ($plan->user_limit * 0.85);
    }

    /** @return array<int, array<string, mixed>> */
    public function fleetVersionMatrix(): array
    {
        return Church::query()
            ->with('latestHealthSnapshot')
            ->get()
            ->map(fn (Church $church) => [
                'church_id' => $church->id,
                'church_name' => $church->name,
                'app_version' => $church->latestHealthSnapshot?->app_version,
                'app_tag' => $church->latestHealthSnapshot?->app_tag,
                'reported_at' => $church->latestHealthSnapshot?->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
