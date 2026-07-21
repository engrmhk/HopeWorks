<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Communications\MessageProviderManager;

class BillingNotificationService
{
    public function __construct(
        protected MessageProviderManager $messageProviderManager,
    ) {}

    public function notifyInvoiceDue(Invoice $invoice): void
    {
        $this->sendForSubscription(
            $invoice->subscription,
            'Invoice due: Hope Works subscription',
            "Invoice #{$invoice->id} for {$invoice->amount} {$invoice->currency} is due on {$invoice->due_date->toDateString()}.",
            ['type' => 'invoice_due', 'invoice_id' => $invoice->id],
        );
    }

    public function notifyPaymentFailed(Subscription $subscription, int $attemptCount): void
    {
        $this->sendForSubscription(
            $subscription,
            'Payment failed: Hope Works subscription',
            "Payment attempt {$attemptCount} failed. We will retry before any service interruption.",
            ['type' => 'payment_failed', 'attempt_count' => $attemptCount],
        );
    }

    public function notifyRenewalUpcoming(Subscription $subscription): void
    {
        $this->sendForSubscription(
            $subscription,
            'Upcoming renewal: Hope Works subscription',
            'Your Hope Works subscription renews on '.$subscription->current_period_end?->toDateString().'.',
            ['type' => 'renewal_upcoming'],
        );
    }

    protected function sendForSubscription(?Subscription $subscription, string $subject, string $body, array $context): void
    {
        if ($subscription === null) {
            return;
        }

        $church = $subscription->church;

        if (! $church instanceof Church) {
            return;
        }

        $to = $church->billing_email
            ?? $church->contact_email
            ?? $church->synod?->contact_email;

        if (! is_string($to) || $to === '') {
            return;
        }

        $this->messageProviderManager->driver()->send($to, $subject, $body, array_merge($context, [
            'church_id' => $church->id,
            'subscription_id' => $subscription->id,
        ]));
    }
}
