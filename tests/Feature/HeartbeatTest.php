<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Synod;
use App\Services\LicenseKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiKey = 'test-api-key-heartbeat';

    protected Church $church;

    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $synod = Synod::create(['name' => 'Test Synod']);
        $plan = Plan::create([
            'name' => 'Pro',
            'price' => 149.00,
            'billing_interval' => 'monthly',
            'module_eligibility' => ['members' => true, 'giving' => true],
        ]);

        $this->church = Church::create([
            'synod_id' => $synod->id,
            'name' => 'Grace Community',
            'subdomain' => 'grace-community',
            'instance_api_key_hash' => Church::hashApiKey($this->apiKey),
        ]);

        $this->subscription = Subscription::create([
            'church_id' => $this->church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::BannerOnly,
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_valid_api_key_returns_license(): void
    {
        $response = $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'enforcement_policy',
                'license_key',
                'expires_at',
                'license_expires_at',
                'current_period_end',
                'grace_started_at',
                'grace_period_days',
                'church_id',
                'plan_id',
            ])
            ->assertJson([
                'status' => SubscriptionStatus::Active->value,
                'church_id' => $this->church->id,
            ]);

        $this->assertSame(
            $this->subscription->fresh()->current_period_end->toIso8601String(),
            $response->json('current_period_end'),
        );

        $licenseKeyService = app(LicenseKeyService::class);
        $payload = $licenseKeyService->decode($response->json('license_key'));

        $this->assertSame($this->church->id, $payload->church_id);
        $this->assertSame($this->subscription->plan_id, $payload->plan_id);
        $this->assertSame(
            $this->subscription->fresh()->current_period_end->toIso8601String(),
            $payload->current_period_end,
        );
        // JWT expires_at is a short re-sync TTL, not the billing period end.
        $this->assertNotSame($response->json('current_period_end'), $response->json('expires_at'));
    }

    public function test_suspended_after_grace_period_elapsed(): void
    {
        $this->subscription->update([
            'status' => SubscriptionStatus::Grace,
            'current_period_end' => now()->subDays(10),
            'grace_started_at' => now()->subDays(8),
            'grace_period_days' => 7,
        ]);

        Carbon::setTestNow(now()->addDay());

        $response = $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ]);

        Carbon::setTestNow();

        $response->assertOk()
            ->assertJson([
                'status' => SubscriptionStatus::Suspended->value,
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => SubscriptionStatus::Suspended->value,
        ]);
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer invalid-key',
        ]);

        $response->assertUnauthorized();
    }
}
