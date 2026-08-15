<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\LicenseKey;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LicenseKeyService;
use App\Services\SubscriptionEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SubscriptionStatusEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_registers_evaluate_statuses_every_minute(): void
    {
        $events = collect(Schedule::events());

        $match = $events->first(
            fn ($event) => str_contains($event->command ?? '', 'subscriptions:evaluate-statuses')
                || str_contains($event->description ?? '', 'subscriptions:evaluate-statuses')
                || str_contains((string) ($event->command ?? ''), 'EvaluateSubscriptionStatusesCommand'),
        );

        $this->assertNotNull($match, 'subscriptions:evaluate-statuses must be registered on the scheduler');
        $this->assertSame('* * * * *', $match->expression);
        $this->assertSame('every minute', config('hopeworks.subscription_enforcement.schedule_human'));
    }

    public function test_force_status_check_transitions_active_with_past_period_end_immediately(): void
    {
        $church = $this->churchWithSubscription(SubscriptionStatus::Active, now()->subDay());

        $result = app(SubscriptionEnforcementService::class)
            ->forceStatusCheckForChurch($church, User::factory()->create());

        $this->assertSame(SubscriptionStatus::Grace, $result->status);
        $this->assertNotNull($result->status_evaluated_at);
        $this->assertTrue($result->status_evaluated_at->greaterThanOrEqualTo(now()->subSeconds(5)));
    }

    public function test_scheduled_command_transitions_grace_to_suspended(): void
    {
        $church = $this->churchWithSubscription(SubscriptionStatus::Grace, now()->subDays(30));
        $church->subscription->update([
            'grace_started_at' => now()->subDays(10),
            'grace_period_days' => 7,
        ]);

        Artisan::call('subscriptions:evaluate-statuses', ['--church' => $church->id]);

        $this->assertSame(SubscriptionStatus::Suspended, $church->subscription()->first()->status);
    }

    public function test_heartbeat_sets_last_heartbeat_and_status_evaluated_timestamps(): void
    {
        $apiKey = 'hw_status_eval_hb';
        $church = $this->churchWithSubscription(SubscriptionStatus::Active, now()->addMonth());
        $church->setInstanceApiKey($apiKey);

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$apiKey,
        ])->assertOk();

        $church->refresh();
        $subscription = $church->subscription()->first();

        $this->assertNotNull($church->last_heartbeat_at);
        $this->assertNotNull($subscription->status_evaluated_at);
    }

    public function test_invalidate_cached_license_revokes_active_jwts(): void
    {
        $church = $this->churchWithSubscription();
        $service = app(LicenseKeyService::class);
        $service->issueForSubscription($church->subscription);
        $service->issueForSubscription($church->subscription);

        $this->assertSame(2, $church->licenseKeys()->whereNull('revoked_at')->count());

        $revoked = $service->revokeAllForChurch($church);

        $this->assertSame(2, $revoked);
        $this->assertSame(0, LicenseKey::query()->where('church_id', $church->id)->whereNull('revoked_at')->count());
    }

    protected function churchWithSubscription(
        SubscriptionStatus $status = SubscriptionStatus::Active,
        $periodEnd = null,
    ): Church {
        $church = Church::create([
            'name' => 'Eval Church',
            'subdomain' => 'eval-'.uniqid(),
            'status' => \App\Enums\ChurchStatus::Active,
        ]);

        $plan = Plan::create([
            'name' => 'Std',
            'price' => 10,
            'billing_interval' => 'monthly',
        ]);

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::BannerOnly,
            'current_period_end' => $periodEnd ?? now()->addMonth(),
            'grace_started_at' => $status === SubscriptionStatus::Grace ? now()->subDays(2) : null,
        ]);

        return $church->fresh(['subscription']);
    }
}
