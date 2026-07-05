<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentEvent;
use App\Models\Subscription;
use App\Services\SubscriptionEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected SubscriptionEnforcementService $subscriptionEnforcementService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = $payload['type'] ?? 'unknown';
        $subscription = $this->resolveSubscription($payload);

        PaymentEvent::create([
            'subscription_id' => $subscription?->id,
            'event_type' => $eventType,
            'gateway_payload' => $payload,
        ]);

        if ($subscription !== null) {
            $this->handleSubscriptionUpdate($subscription, $eventType, $payload);
        }

        return response()->json(['received' => true]);
    }

    protected function resolveSubscription(array $payload): ?Subscription
    {
        $data = $payload['data']['object'] ?? [];
        $customerId = $data['customer'] ?? null;

        if ($customerId === null) {
            $subscriptionId = $data['subscription'] ?? $data['id'] ?? null;

            if (is_string($subscriptionId) && str_starts_with($subscriptionId, 'sub_')) {
                return Subscription::query()
                    ->where('payment_gateway_customer_id', $subscriptionId)
                    ->first();
            }

            return null;
        }

        return Subscription::query()
            ->where('payment_gateway_customer_id', $customerId)
            ->first();
    }

    protected function handleSubscriptionUpdate(Subscription $subscription, string $eventType, array $payload): void
    {
        match ($eventType) {
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($subscription, $payload),
            'invoice.payment_failed' => $this->handlePaymentFailed($subscription),
            'customer.subscription.deleted' => $subscription->update(['status' => SubscriptionStatus::Cancelled]),
            default => null,
        };
    }

    protected function handlePaymentSucceeded(Subscription $subscription, array $payload): void
    {
        $periodEnd = data_get($payload, 'data.object.lines.data.0.period.end');

        $this->subscriptionEnforcementService->activate(
            $subscription,
            $periodEnd ? Carbon::createFromTimestamp($periodEnd) : now()->addMonth(),
        );
    }

    protected function handlePaymentFailed(Subscription $subscription): void
    {
        if ($subscription->status === SubscriptionStatus::Active) {
            $this->subscriptionEnforcementService->transition($subscription, SubscriptionStatus::Grace, [
                'grace_started_at' => now(),
            ]);
        }
    }
}
