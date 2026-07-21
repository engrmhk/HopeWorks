<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\BillingNotificationService;
use App\Services\Communications\MessageProviderManager;
use App\Services\Communications\Providers\MailMessageProvider;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class StripeBillingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $webhookSecret = 'whsec_test_secret';

    protected string $apiKey = 'hw_portal_test_key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hopeworks.stripe.webhook_secret' => $this->webhookSecret,
            'hopeworks.stripe.dunning_max_attempts' => 3,
            'hopeworks.stripe.secret' => 'sk_test_fake',
            'hopeworks.billing.currency' => 'USD',
            'hopeworks.messaging.driver' => 'mail',
        ]);
    }

    protected function signStripePayload(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return 't='.$timestamp.',v1='.$signature;
    }

    public function test_checkout_session_completed_activates_subscription(): void
    {
        $subscription = $this->createSubscription();

        $payload = json_encode([
            'id' => 'evt_checkout',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'customer' => 'cus_from_checkout',
                    'subscription' => 'sub_from_checkout',
                    'client_reference_id' => (string) $subscription->id,
                    'metadata' => [
                        'subscription_id' => (string) $subscription->id,
                        'church_id' => (string) $subscription->church_id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Stripe-Signature' => $this->signStripePayload($payload),
            ],
            $payload,
        );

        $response->assertOk();

        $subscription->refresh();
        $this->assertSame('cus_from_checkout', $subscription->payment_gateway_customer_id);
        $this->assertSame('sub_from_checkout', $subscription->payment_gateway_subscription_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_payment_failed_webhook_triggers_grace_after_max_dunning(): void
    {
        $subscription = $this->createSubscription();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $payload = json_encode([
                'id' => 'evt_fail_'.$attempt,
                'type' => 'invoice.payment_failed',
                'data' => [
                    'object' => [
                        'id' => 'in_fail_'.$attempt,
                        'customer' => 'cus_test123',
                        'subscription' => 'sub_test123',
                        'attempt_count' => $attempt,
                        'amount_due' => 9900,
                        'currency' => 'usd',
                        'status' => 'open',
                        'due_date' => now()->timestamp,
                    ],
                ],
            ], JSON_THROW_ON_ERROR);

            $this->call(
                'POST',
                '/api/v1/webhooks/stripe',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_Stripe-Signature' => $this->signStripePayload($payload),
                ],
                $payload,
            )->assertOk();
        }

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);
        $this->assertNotNull($subscription->grace_started_at);
        $this->assertSame(3, $subscription->dunning_attempt_count);
    }

    public function test_billing_portal_rejects_invalid_api_key(): void
    {
        $this->postJson('/api/v1/billing/portal', [], [
            'Authorization' => 'Bearer invalid',
        ])->assertUnauthorized();
    }

    public function test_billing_portal_returns_church_specific_url(): void
    {
        $subscription = $this->createSubscription();
        $church = $subscription->church;
        $church->setInstanceApiKey($this->apiKey);

        $mock = Mockery::mock(StripeBillingService::class);
        $mock->shouldReceive('createBillingPortalSession')
            ->once()
            ->withArgs(fn (Church $c) => $c->is($church))
            ->andReturn(['url' => 'https://billing.stripe.com/p/session/test_church_'.$church->id]);

        $this->app->instance(StripeBillingService::class, $mock);

        $this->postJson('/api/v1/billing/portal', [
            'return_url' => 'https://church.example/billing',
        ], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->assertOk()
            ->assertJson([
                'url' => 'https://billing.stripe.com/p/session/test_church_'.$church->id,
            ]);
    }

    public function test_lbp_currency_is_rejected_by_stripe_billing_service(): void
    {
        config(['hopeworks.billing.currency' => 'LBP']);

        $this->expectException(ValidationException::class);

        app(StripeBillingService::class)->assertStripeSupportedCurrency();
    }

    public function test_billing_notifications_use_control_plane_mail_provider(): void
    {
        $this->assertInstanceOf(
            MailMessageProvider::class,
            app(MessageProviderManager::class)->driver('mail'),
        );

        $subscription = $this->createSubscription();
        $subscription->church->update(['billing_email' => 'finance@church.test']);

        $spy = Mockery::spy(MailMessageProvider::class);
        $this->app->instance(MailMessageProvider::class, $spy);
        config(['hopeworks.messaging.driver' => 'mail']);

        app(BillingNotificationService::class)->notifyPaymentFailed($subscription->fresh(['church.synod']), 1);

        $spy->shouldHaveReceived('send')
            ->once()
            ->withArgs(fn (string $to, string $subject) => $to === 'finance@church.test'
                && str_contains($subject, 'Payment failed'));
    }

    protected function createSubscription(): Subscription
    {
        $church = Church::create([
            'name' => 'Billing Church',
            'subdomain' => 'billing-church-'.uniqid(),
            'billing_email' => 'billing@church.test',
        ]);

        $plan = Plan::create([
            'name' => 'Pro',
            'price' => 99,
            'billing_interval' => 'monthly',
            'stripe_price_id' => 'price_test_123',
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
}
