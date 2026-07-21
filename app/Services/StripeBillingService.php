<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\StripeClient;

/**
 * Official Stripe PHP SDK integration for Checkout + Customer Portal.
 *
 * Currency note (Gap Report / billing): Stripe does not support LBP.
 * If BILLING_CURRENCY=LBP, checkout/portal creation is rejected — use a
 * regional processor before onboarding LBP-paying clients.
 */
class StripeBillingService
{
    public function __construct(
        protected ?StripeClient $stripe = null,
    ) {}

    public function client(): StripeClient
    {
        if ($this->stripe instanceof StripeClient) {
            return $this->stripe;
        }

        $secret = config('hopeworks.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'stripe' => 'STRIPE_SECRET is not configured.',
            ]);
        }

        return $this->stripe = new StripeClient($secret);
    }

    public function assertStripeSupportedCurrency(): void
    {
        $currency = strtoupper((string) config('hopeworks.billing.currency', 'USD'));

        // Stripe does not support LBP; regional processor required (see config/hopeworks.php).
        if ($currency === 'LBP') {
            throw ValidationException::withMessages([
                'currency' => 'LBP is not supported by Stripe. Configure a regional processor before live LBP billing.',
            ]);
        }
    }

    /**
     * @return array{url: string, session_id: string}
     */
    public function createCheckoutSession(Subscription $subscription, ?Plan $plan = null): array
    {
        $this->assertStripeSupportedCurrency();

        $subscription->loadMissing(['church', 'plan']);
        $church = $subscription->church;
        $plan ??= $subscription->plan;

        if (! $church instanceof Church || ! $plan instanceof Plan) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription must belong to a church and plan.',
            ]);
        }

        if (blank($plan->stripe_price_id)) {
            throw ValidationException::withMessages([
                'plan' => "Plan [{$plan->name}] is missing stripe_price_id.",
            ]);
        }

        $customerId = $subscription->payment_gateway_customer_id;

        if (blank($customerId)) {
            $customer = $this->client()->customers->create([
                'name' => $church->name,
                'email' => $church->billing_email ?? $church->contact_email,
                'metadata' => [
                    'church_id' => (string) $church->id,
                    'subscription_id' => (string) $subscription->id,
                ],
            ]);
            $customerId = $customer->id;
            $subscription->update(['payment_gateway_customer_id' => $customerId]);
        }

        /** @var Session $session */
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $subscription->id,
            'line_items' => [
                [
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ],
            ],
            'success_url' => config('hopeworks.stripe.checkout_success_url'),
            'cancel_url' => config('hopeworks.stripe.checkout_cancel_url'),
            'metadata' => [
                'church_id' => (string) $church->id,
                'subscription_id' => (string) $subscription->id,
                'plan_id' => (string) $plan->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'church_id' => (string) $church->id,
                    'subscription_id' => (string) $subscription->id,
                ],
            ],
        ]);

        return [
            'url' => (string) $session->url,
            'session_id' => (string) $session->id,
        ];
    }

    /**
     * @return array{url: string}
     */
    public function createBillingPortalSession(Church $church, ?string $returnUrl = null): array
    {
        $this->assertStripeSupportedCurrency();

        $subscription = $church->subscription;

        if ($subscription === null || blank($subscription->payment_gateway_customer_id)) {
            throw ValidationException::withMessages([
                'customer' => 'No Stripe customer is linked to this church yet.',
            ]);
        }

        /** @var PortalSession $session */
        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $subscription->payment_gateway_customer_id,
            'return_url' => $returnUrl ?: config('hopeworks.stripe.portal_return_url'),
        ]);

        return ['url' => (string) $session->url];
    }

    public function applyCheckoutSessionCompleted(array $sessionObject): ?Subscription
    {
        $subscriptionId = $sessionObject['metadata']['subscription_id']
            ?? $sessionObject['client_reference_id']
            ?? null;

        if ($subscriptionId === null) {
            return null;
        }

        $subscription = Subscription::query()->find($subscriptionId);

        if ($subscription === null) {
            return null;
        }

        $updates = [];

        if (! empty($sessionObject['customer'])) {
            $updates['payment_gateway_customer_id'] = $sessionObject['customer'];
        }

        if (! empty($sessionObject['subscription'])) {
            $updates['payment_gateway_subscription_id'] = $sessionObject['subscription'];
        }

        if ($updates !== []) {
            $subscription->update($updates);
        }

        return $subscription->fresh();
    }
}
