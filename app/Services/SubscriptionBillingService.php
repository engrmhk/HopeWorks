<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

class SubscriptionBillingService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function upgradePlan(Subscription $subscription, Plan $newPlan, ?float $proratedAmount = null): Subscription
    {
        $before = [
            'plan_id' => $subscription->plan_id,
            'pending_plan_id' => $subscription->pending_plan_id,
        ];

        $subscription->plan_id = $newPlan->id;
        $subscription->pending_plan_id = null;
        $subscription->pending_plan_effective_at = null;
        $subscription->save();

        if ($proratedAmount !== null && $proratedAmount > 0) {
            Invoice::create([
                'subscription_id' => $subscription->id,
                'amount' => $proratedAmount,
                'currency' => config('hopeworks.billing.currency', 'USD'),
                'status' => InvoiceStatus::Pending,
                'due_date' => now()->toDateString(),
            ]);
        }

        $this->auditLogService->log(
            actor: auth()->user(),
            action: 'subscription.plan_upgraded',
            subject: $subscription,
            before: $before,
            after: [
                'plan_id' => $newPlan->id,
                'prorated_amount' => $proratedAmount,
            ],
            ip: request()?->ip(),
            highVisibility: true,
        );

        return $subscription->fresh(['plan']);
    }

    public function scheduleDowngrade(Subscription $subscription, Plan $newPlan): Subscription
    {
        $before = [
            'plan_id' => $subscription->plan_id,
            'pending_plan_id' => $subscription->pending_plan_id,
        ];

        $subscription->pending_plan_id = $newPlan->id;
        $subscription->pending_plan_effective_at = $subscription->current_period_end ?? now()->addMonth();
        $subscription->save();

        $this->auditLogService->log(
            actor: auth()->user(),
            action: 'subscription.plan_downgrade_scheduled',
            subject: $subscription,
            before: $before,
            after: [
                'pending_plan_id' => $newPlan->id,
                'pending_plan_effective_at' => $subscription->pending_plan_effective_at?->toIso8601String(),
            ],
            ip: request()?->ip(),
            highVisibility: true,
        );

        return $subscription->fresh(['plan', 'pendingPlan']);
    }

    public function applyPendingPlanChanges(Subscription $subscription): Subscription
    {
        if ($subscription->pending_plan_id === null || $subscription->pending_plan_effective_at === null) {
            return $subscription;
        }

        if (now()->lt($subscription->pending_plan_effective_at)) {
            return $subscription;
        }

        $subscription->plan_id = $subscription->pending_plan_id;
        $subscription->pending_plan_id = null;
        $subscription->pending_plan_effective_at = null;
        $subscription->save();

        return $subscription->fresh(['plan']);
    }

    public function syncInvoiceFromGateway(Subscription $subscription, array $invoiceData): Invoice
    {
        $gatewayId = $invoiceData['id'] ?? null;
        $amount = ($invoiceData['amount_due'] ?? $invoiceData['total'] ?? 0) / 100;
        $status = match ($invoiceData['status'] ?? 'open') {
            'paid' => InvoiceStatus::Paid,
            'uncollectible', 'void' => InvoiceStatus::Failed,
            default => InvoiceStatus::Pending,
        };

        $dueDate = isset($invoiceData['due_date'])
            ? Carbon::createFromTimestamp($invoiceData['due_date'])->toDateString()
            : now()->toDateString();

        return Invoice::updateOrCreate(
            ['gateway_invoice_id' => $gatewayId],
            [
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => strtoupper($invoiceData['currency'] ?? config('hopeworks.billing.currency', 'USD')),
                'status' => $status,
                'source' => \App\Enums\InvoiceSource::Stripe,
                'payment_method' => \App\Enums\PaymentMethod::Stripe,
                'due_date' => $dueDate,
                'paid_at' => $status === InvoiceStatus::Paid ? now() : null,
            ],
        );
    }
}
