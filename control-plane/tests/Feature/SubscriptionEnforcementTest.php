<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\ControlPlaneAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_to_grace_transition_writes_audit_log(): void
    {
        $church = Church::create([
            'name' => 'Test Church',
            'subdomain' => 'test-church',
        ]);

        $plan = Plan::create([
            'name' => 'Basic',
            'price' => 49.00,
            'billing_interval' => 'monthly',
        ]);

        $subscription = Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::BannerOnly,
            'current_period_end' => now()->subDay(),
        ]);

        $service = app(SubscriptionEnforcementService::class);
        $result = $service->evaluate($subscription);

        $this->assertSame(SubscriptionStatus::Grace, $result->status);
        $this->assertNotNull($result->grace_started_at);

        $this->assertDatabaseHas('control_plane_audit_logs', [
            'action' => 'subscription.status_changed',
            'subject_type' => $subscription->getMorphClass(),
            'subject_id' => $subscription->id,
        ]);

        $auditLog = ControlPlaneAuditLog::where('action', 'subscription.status_changed')->first();
        $this->assertSame(SubscriptionStatus::Active->value, $auditLog->before['status']);
        $this->assertSame(SubscriptionStatus::Grace->value, $auditLog->after['status']);
    }

    public function test_grace_to_suspended_transition_writes_audit_log(): void
    {
        $church = Church::create([
            'name' => 'Grace Church',
            'subdomain' => 'grace-church',
        ]);

        $plan = Plan::create([
            'name' => 'Basic',
            'price' => 49.00,
            'billing_interval' => 'monthly',
        ]);

        Carbon::setTestNow('2026-01-01 00:00:00');

        $subscription = Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Grace,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::ReadOnly,
            'grace_started_at' => now()->subDays(7),
            'current_period_end' => now()->subDays(14),
        ]);

        Carbon::setTestNow('2026-01-08 00:00:01');

        $service = app(SubscriptionEnforcementService::class);
        $result = $service->evaluate($subscription);

        Carbon::setTestNow();

        $this->assertSame(SubscriptionStatus::Suspended, $result->status);

        $this->assertDatabaseHas('control_plane_audit_logs', [
            'action' => 'subscription.status_changed',
            'subject_id' => $subscription->id,
        ]);

        $auditLog = ControlPlaneAuditLog::where('action', 'subscription.status_changed')
            ->latest('id')
            ->first();

        $this->assertSame(SubscriptionStatus::Grace->value, $auditLog->before['status']);
        $this->assertSame(SubscriptionStatus::Suspended->value, $auditLog->after['status']);
    }
}
