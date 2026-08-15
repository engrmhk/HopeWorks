<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\ClientHealthSnapshot;
use App\Models\ControlPlaneAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Synod;
use App\Models\User;
use App\Services\DunningService;
use App\Services\StripeWebhookVerifier;
use App\Services\SubscriptionBillingService;
use App\Services\TenantHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase7BillingAndPlatformOpsTest extends TestCase
{
    use RefreshDatabase;

    protected string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hopeworks.stripe.webhook_secret' => $this->webhookSecret,
            'hopeworks.stripe.dunning_max_attempts' => 3,
            'hopeworks.tenant_health.at_risk_no_login_days' => 30,
        ]);
    }

    protected function signStripePayload(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return 't='.$timestamp.',v1='.$signature;
    }

    protected function createSubscription(): Subscription
    {
        $church = Church::create([
            'name' => 'Billing Church',
            'subdomain' => 'billing-church',
        ]);

        $plan = Plan::create([
            'name' => 'Pro',
            'price' => 99,
            'billing_interval' => 'monthly',
        ]);

        return Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::BannerOnly,
            'payment_gateway_customer_id' => 'cus_test123',
            'payment_gateway_subscription_id' => 'sub_test123',
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_stripe_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson('/api/v1/webhooks/stripe', ['type' => 'invoice.paid']);

        $response->assertForbidden();
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/v1/webhooks/stripe', ['type' => 'invoice.paid'], [
            'Stripe-Signature' => 't='.time().',v1=invalid',
        ]);

        $response->assertForbidden();
    }

    public function test_dunning_retries_before_grace_period_starts(): void
    {
        $subscription = $this->createSubscription();
        $dunning = app(DunningService::class);

        $dunning->recordFailedAttempt($subscription, 1);
        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->grace_started_at);
        $this->assertSame(1, $subscription->dunning_attempt_count);

        $dunning->recordFailedAttempt($subscription, 2);
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);

        $dunning->recordFailedAttempt($subscription, 3);
        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);
        $this->assertNotNull($subscription->grace_started_at);
        $this->assertNotNull($subscription->dunning_exhausted_at);
    }

    public function test_payment_failed_webhook_sequences_dunning_before_grace(): void
    {
        $subscription = $this->createSubscription();

        $payload = json_encode([
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test1',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123',
                    'attempt_count' => 1,
                    'amount_due' => 9900,
                    'currency' => 'usd',
                    'status' => 'open',
                    'due_date' => now()->timestamp,
                ],
            ],
        ]);

        $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $this->signStripePayload($payload),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        )->assertOk();

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(1, $subscription->dunning_attempt_count);
    }

    public function test_mid_cycle_upgrade_creates_prorated_invoice_and_downgrade_schedules_next_cycle(): void
    {
        $subscription = $this->createSubscription();
        $basic = Plan::create(['name' => 'Basic', 'price' => 49, 'billing_interval' => 'monthly']);
        $pro = Plan::create(['name' => 'Enterprise', 'price' => 199, 'billing_interval' => 'monthly']);

        $subscription->update(['plan_id' => $basic->id]);

        $billing = app(SubscriptionBillingService::class);
        $billing->upgradePlan($subscription, $pro, 25.50);

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'amount' => 25.50,
        ]);
        $this->assertSame($pro->id, $subscription->fresh()->plan_id);

        $billing->scheduleDowngrade($subscription->fresh(), $basic);
        $fresh = $subscription->fresh();

        $this->assertSame($pro->id, $fresh->plan_id);
        $this->assertSame($basic->id, $fresh->pending_plan_id);
        $this->assertNotNull($fresh->pending_plan_effective_at);
    }

    public function test_heartbeat_records_app_version_tag(): void
    {
        $apiKey = 'heartbeat-metrics-key';
        $church = Church::create([
            'name' => 'Metrics Church',
            'subdomain' => 'metrics-church',
            'instance_api_key_hash' => Church::hashApiKey($apiKey),
        ]);

        $plan = Plan::create(['name' => 'Basic', 'price' => 49, 'billing_interval' => 'monthly']);

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::BannerOnly,
            'current_period_end' => now()->addMonth(),
        ]);

        $this->postJson('/api/v1/heartbeat', [
            'app_version' => '1.2.3',
            'app_tag' => 'v1.2.3-rc1',
            'active_user_count' => 12,
            'storage_bytes' => 1024,
        ], [
            'Authorization' => 'Bearer '.$apiKey,
        ])->assertOk();

        $this->assertDatabaseHas('client_health_snapshots', [
            'church_id' => $church->id,
            'app_version' => '1.2.3',
            'app_tag' => 'v1.2.3-rc1',
        ]);
    }

    public function test_at_risk_client_flagged_when_no_recent_logins(): void
    {
        $church = Church::create(['name' => 'Stale', 'subdomain' => 'stale']);
        $snapshot = ClientHealthSnapshot::create([
            'church_id' => $church->id,
            'storage_bytes' => 1000,
            'active_user_count' => 2,
            'last_login_at' => now()->subDays(45),
            'app_version' => '1.0.0',
            'app_tag' => 'v1.0.0',
        ]);

        $this->assertTrue(app(TenantHealthService::class)->isAtRisk($snapshot));
    }

    public function test_audit_log_high_visibility_filter(): void
    {
        ControlPlaneAuditLog::create([
            'action' => 'church.updated',
            'high_visibility' => false,
        ]);

        ControlPlaneAuditLog::create([
            'action' => 'impersonation.token_issued',
            'high_visibility' => true,
            'correlation_id' => 'corr-123',
        ]);

        $highVisibility = ControlPlaneAuditLog::query()->where('high_visibility', true)->get();

        $this->assertCount(1, $highVisibility);
        $this->assertSame('impersonation.token_issued', $highVisibility->first()->action);
    }

    public function test_stripe_verifier_accepts_valid_signature(): void
    {
        $payload = json_encode(['type' => 'test']);
        $verifier = app(StripeWebhookVerifier::class);

        $request = \Illuminate\Http\Request::create(
            '/api/v1/webhooks/stripe',
            'POST',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $this->signStripePayload($payload)],
            $payload,
        );

        $decoded = $verifier->verify($request);
        $this->assertSame('test', $decoded['type']);
    }

    public function test_offboarding_readme_documents_schema_for_external_vendor(): void
    {
        $service = new \App\Services\TenantOffboardingService(app(\App\Services\AuditLogService::class));
        $readme = (new \ReflectionMethod($service, 'buildReadme'))
            ->invoke($service, [
                'churches' => [
                    ['name' => 'Alpha Church', 'directory' => 'alpha'],
                    ['name' => 'Beta Church', 'directory' => 'beta'],
                ],
            ]);

        $this->assertStringContainsString('people', $readme);
        $this->assertStringContainsString('giving_transactions', $readme);
        $this->assertStringContainsString('schema_version', $readme);
        $this->assertStringContainsString('Alpha Church', $readme);
        $this->assertStringContainsString('Beta Church', $readme);
    }
}
