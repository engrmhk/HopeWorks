<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Synod;
use App\Services\LicenseKeyService;
use App\Support\LicenseJwtSecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

    public function test_heartbeat_uses_database_jwt_secret_when_env_is_empty(): void
    {
        Cache::forget(LicenseJwtSecret::CACHE_KEY);
        PlatformSetting::query()->delete();
        config(['license.jwt_secret' => '']);

        LicenseJwtSecret::save(str_repeat('a', 40));

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->assertOk();
    }

    public function test_short_jwt_secret_returns_503_without_exception_trace(): void
    {
        Cache::forget(LicenseJwtSecret::CACHE_KEY);
        PlatformSetting::query()->delete();
        config(['license.jwt_secret' => '']);

        $response = $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('code', 'license_jwt_secret_too_short')
            ->assertJsonMissing(['exception', 'file', 'trace']);

        $this->assertStringContainsString(
            'The Instance API key is not the JWT secret.',
            (string) $response->json('message'),
        );
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer invalid-key',
        ]);

        $response->assertUnauthorized();
    }

    public function test_inactive_synod_locks_member_churches_and_sends_notice(): void
    {
        $this->church->synod->update([
            'status' => \App\Enums\SynodStatus::Inactive,
            'platform_notice' => 'Synod paused by platform.',
        ]);

        $response = $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('enforcement_policy', EnforcementPolicy::FullLock->value)
            ->assertJsonPath('synod_status', 'inactive')
            ->assertJsonPath('platform_notices.0.message', 'Synod paused by platform.');

        $payload = app(LicenseKeyService::class)->decode($response->json('license_key'));
        $this->assertSame(EnforcementPolicy::FullLock->value, $payload->enforcement_policy);
    }

    public function test_synod_banner_is_sent_to_member_churches(): void
    {
        $this->church->synod->update([
            'enforcement_policy' => EnforcementPolicy::ReadOnly,
            'platform_notice' => 'Please update your giving reports.',
            'notice_severity' => \App\Enums\NoticeSeverity::Warning,
        ]);

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->assertOk()
            ->assertJsonPath('enforcement_policy', EnforcementPolicy::ReadOnly->value)
            ->assertJsonPath('platform_notices.0.scope', 'synod')
            ->assertJsonPath('platform_notices.0.message', 'Please update your giving reports.');
    }

    public function test_synod_host_church_can_heartbeat_without_subscription(): void
    {
        $hostKey = 'hw_synod_host_key';
        $host = Church::create([
            'synod_id' => $this->church->synod_id,
            'name' => 'Synod HQ',
            'subdomain' => 'synod-hq',
            'is_synod_host' => true,
            'instance_api_key_hash' => Church::hashApiKey($hostKey),
        ]);

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$hostKey,
        ])->assertOk()
            ->assertJsonPath('church_id', $host->id)
            ->assertJsonPath('plan_id', null)
            ->assertJsonPath('status', SubscriptionStatus::Active->value);
    }

    public function test_synod_api_key_heartbeats_without_subscription(): void
    {
        $synodKey = 'hw_synod_connection_key';
        $synod = $this->church->synod;
        $synod->setInstanceApiKey($synodKey);

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$synodKey,
        ])->assertOk()
            ->assertJsonPath('church_id', null)
            ->assertJsonPath('synod_id', $synod->id)
            ->assertJsonPath('synod_status', 'active');
    }
}
