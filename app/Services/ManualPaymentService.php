<?php

namespace App\Services;

use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records out-of-band payments (cash / bank transfer / card charged elsewhere).
 * Drives the same subscription status machine as Stripe webhooks so Core App
 * heartbeat only ever sees status + current_period_end.
 */
class ManualPaymentService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected SubscriptionEnforcementService $subscriptionEnforcementService,
        protected DunningService $dunningService,
    ) {}

    public function hasActiveStripeSubscription(Subscription $subscription): bool
    {
        return filled($subscription->payment_gateway_subscription_id);
    }

    public function stripeConflictWarningMessage(): string
    {
        return 'This church has an active Stripe subscription. Recording a manual payment here does not cancel or interact with Stripe — confirm this is intentional (e.g. an out-of-band top-up).';
    }

    /**
     * @param  array{
     *     amount: float|int|string,
     *     currency?: string,
     *     payment_method: string|PaymentMethod,
     *     reference_note?: string|null,
     *     payment_date: string|\DateTimeInterface,
     * }  $data
     * @return array{invoice: Invoice, subscription: Subscription, period_end: Carbon, stripe_warning: bool}
     */
    public function record(
        Subscription $subscription,
        array $data,
        Authenticatable|User|null $actor = null,
    ): array {
        $method = $data['payment_method'] instanceof PaymentMethod
            ? $data['payment_method']
            : PaymentMethod::from((string) $data['payment_method']);

        if ($method === PaymentMethod::Stripe) {
            throw ValidationException::withMessages([
                'payment_method' => 'Use Stripe Checkout/webhooks for Stripe payments; manual recording is for cash, bank transfer, or card_manual only.',
            ]);
        }

        $paymentDate = Carbon::parse($data['payment_date'])->startOfDay();
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $currency = strtoupper((string) ($data['currency'] ?? config('hopeworks.billing.currency', 'USD')));
        $stripeWarning = $this->hasActiveStripeSubscription($subscription);
        $periodEnd = $this->calculateNewPeriodEnd($subscription, $paymentDate);
        $before = [
            'status' => $subscription->status->value,
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'grace_started_at' => $subscription->grace_started_at?->toIso8601String(),
        ];

        $result = DB::transaction(function () use (
            $subscription,
            $amount,
            $currency,
            $method,
            $data,
            $paymentDate,
            $periodEnd,
            $actor,
            $before,
            $stripeWarning,
        ) {
            $invoice = Invoice::query()->create([
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => InvoiceStatus::Paid,
                'source' => InvoiceSource::Manual,
                'payment_method' => $method,
                'reference_note' => $data['reference_note'] ?? null,
                'recorded_by' => $actor?->getAuthIdentifier(),
                'due_date' => $paymentDate->toDateString(),
                'paid_at' => $paymentDate->copy()->setTimeFrom(now()),
            ]);

            $this->dunningService->clearDunning($subscription->fresh());
            $activated = $this->subscriptionEnforcementService->activate(
                $subscription->fresh(),
                $periodEnd,
            );

            $this->auditLogService->log(
                actor: $actor,
                action: 'subscription.manual_payment_recorded',
                subject: $activated,
                before: $before,
                after: [
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $method->value,
                    'reference_note' => $data['reference_note'] ?? null,
                    'payment_date' => $paymentDate->toDateString(),
                    'status' => $activated->status->value,
                    'current_period_end' => $activated->current_period_end?->toIso8601String(),
                    'stripe_subscription_present' => $stripeWarning,
                ],
                ip: request()?->ip(),
                highVisibility: true,
            );

            return [
                'invoice' => $invoice,
                'subscription' => $activated,
                'period_end' => $periodEnd,
                'stripe_warning' => $stripeWarning,
            ];
        });

        return $result;
    }

    public function calculateNewPeriodEnd(Subscription $subscription, Carbon $paymentDate): Carbon
    {
        $subscription->loadMissing('plan');
        $interval = $subscription->plan?->billing_interval ?? 'monthly';

        $currentEnd = $subscription->current_period_end;
        $extendFromExisting = $subscription->status === SubscriptionStatus::Active
            && $currentEnd !== null
            && $currentEnd->greaterThan($paymentDate);

        $base = $extendFromExisting
            ? $currentEnd->copy()
            : $paymentDate->copy()->startOfDay();

        return match ($interval) {
            'yearly' => $base->addYear(),
            default => $base->addMonth(),
        };
    }
}
