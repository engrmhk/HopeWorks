<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentEvent;
use App\Models\Subscription;
use App\Services\BillingNotificationService;
use App\Services\DunningService;
use App\Services\InvoicePdfService;
use App\Services\StripeBillingService;
use App\Services\StripeWebhookVerifier;
use App\Services\SubscriptionBillingService;
use App\Services\SubscriptionEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeWebhookVerifier $stripeWebhookVerifier,
        protected StripeBillingService $stripeBillingService,
        protected SubscriptionEnforcementService $subscriptionEnforcementService,
        protected SubscriptionBillingService $subscriptionBillingService,
        protected DunningService $dunningService,
        protected BillingNotificationService $billingNotificationService,
        protected InvoicePdfService $invoicePdfService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $this->stripeWebhookVerifier->verify($request);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $eventType = $payload['type'] ?? 'unknown';
        $subscription = $this->resolveSubscription($payload);

        PaymentEvent::create([
            'subscription_id' => $subscription?->id,
            'event_type' => $eventType,
            'gateway_payload' => $payload,
        ]);

        if ($eventType === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($payload);

            return response()->json(['received' => true]);
        }

        if ($subscription !== null) {
            $this->handleEvent($subscription, $eventType, $payload);
        }

        return response()->json(['received' => true]);
    }

    protected function handleEvent(Subscription $subscription, string $eventType, array $payload): void
    {
        match ($eventType) {
            'invoice.paid', 'invoice.payment_succeeded' => $this->handleInvoicePaid($subscription, $payload),
            'invoice.payment_failed' => $this->handlePaymentFailed($subscription, $payload),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($subscription, $payload),
            'customer.subscription.deleted' => $subscription->update(['status' => SubscriptionStatus::Cancelled]),
            default => null,
        };
    }

    protected function handleCheckoutCompleted(array $payload): void
    {
        $session = $payload['data']['object'] ?? [];
        $subscription = $this->stripeBillingService->applyCheckoutSessionCompleted($session);

        if ($subscription === null) {
            return;
        }

        $this->dunningService->clearDunning($subscription);
        $this->subscriptionEnforcementService->activate(
            $subscription,
            now()->addMonth(),
        );
    }

    protected function handleInvoicePaid(Subscription $subscription, array $payload): void
    {
        $invoiceData = $payload['data']['object'] ?? [];
        $invoice = $this->subscriptionBillingService->syncInvoiceFromGateway($subscription, $invoiceData);
        $this->invoicePdfService->generate($invoice);

        $periodEnd = data_get($invoiceData, 'lines.data.0.period.end');

        $this->dunningService->clearDunning($subscription);
        $this->subscriptionEnforcementService->activate(
            $subscription,
            $periodEnd ? Carbon::createFromTimestamp($periodEnd) : now()->addMonth(),
        );
        $this->subscriptionBillingService->applyPendingPlanChanges($subscription->fresh());
    }

    protected function handlePaymentFailed(Subscription $subscription, array $payload): void
    {
        $invoiceData = $payload['data']['object'] ?? [];
        $attemptCount = (int) ($invoiceData['attempt_count'] ?? 1);

        $this->subscriptionBillingService->syncInvoiceFromGateway($subscription, array_merge($invoiceData, [
            'status' => 'open',
        ]));

        $this->billingNotificationService->notifyPaymentFailed($subscription, $attemptCount);
        $this->dunningService->recordFailedAttempt($subscription->fresh(), $attemptCount);
    }

    protected function handleSubscriptionUpdated(Subscription $subscription, array $payload): void
    {
        $data = $payload['data']['object'] ?? [];
        $periodEnd = $data['current_period_end'] ?? null;

        if ($periodEnd !== null) {
            $subscription->update([
                'current_period_end' => Carbon::createFromTimestamp($periodEnd),
                'payment_gateway_subscription_id' => $data['id'] ?? $subscription->payment_gateway_subscription_id,
            ]);
        }

        $this->subscriptionBillingService->applyPendingPlanChanges($subscription->fresh());
    }

    protected function resolveSubscription(array $payload): ?Subscription
    {
        $data = $payload['data']['object'] ?? [];

        $metaSubscriptionId = $data['metadata']['subscription_id'] ?? $data['client_reference_id'] ?? null;

        if (is_numeric($metaSubscriptionId)) {
            $byMeta = Subscription::query()->find((int) $metaSubscriptionId);

            if ($byMeta !== null) {
                return $byMeta;
            }
        }

        $subscriptionId = $data['subscription'] ?? null;

        if (is_string($subscriptionId) && str_starts_with($subscriptionId, 'sub_')) {
            return Subscription::query()
                ->where('payment_gateway_subscription_id', $subscriptionId)
                ->first();
        }

        $customerId = $data['customer'] ?? null;

        if ($customerId !== null) {
            return Subscription::query()
                ->where('payment_gateway_customer_id', $customerId)
                ->first();
        }

        if (isset($data['id']) && str_starts_with((string) $data['id'], 'sub_')) {
            return Subscription::query()
                ->where('payment_gateway_subscription_id', $data['id'])
                ->first();
        }

        return null;
    }
}
