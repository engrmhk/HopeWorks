<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Filament\Actions\RecordManualPaymentAction;
use App\Models\Church;
use App\Models\ControlPlaneAuditLog;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ManualPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ManualPaymentRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiKey = 'hw_manual_payment_key';

    public function test_manual_payment_reactivates_suspended_church_and_heartbeat_reflects_it(): void
    {
        $actor = User::factory()->withManualPaymentPermission()->create();
        $subscription = $this->createSubscription(SubscriptionStatus::Suspended, now()->subDays(20));
        $church = $subscription->church;
        $church->setInstanceApiKey($this->apiKey);

        $paymentDate = Carbon::parse('2026-07-15');

        $result = app(ManualPaymentService::class)->record($subscription, [
            'amount' => 99,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'reference_note' => 'Cash receipt #44',
            'payment_date' => $paymentDate,
        ], $actor);

        $this->assertSame(SubscriptionStatus::Active, $result['subscription']->status);
        $this->assertTrue($result['period_end']->equalTo($paymentDate->copy()->addMonth()));
        $this->assertDatabaseHas('invoices', [
            'id' => $result['invoice']->id,
            'status' => InvoiceStatus::Paid->value,
            'source' => InvoiceSource::Manual->value,
            'payment_method' => PaymentMethod::Cash->value,
            'reference_note' => 'Cash receipt #44',
            'recorded_by' => $actor->id,
        ]);

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->assertOk()
            ->assertJson([
                'status' => SubscriptionStatus::Active->value,
                'church_id' => $church->id,
            ]);
    }

    public function test_active_subscription_extends_from_existing_period_end(): void
    {
        $existingEnd = Carbon::parse('2026-08-01')->endOfDay()->startOfSecond();
        $subscription = $this->createSubscription(SubscriptionStatus::Active, $existingEnd);
        $paymentDate = Carbon::parse('2026-07-20');

        $result = app(ManualPaymentService::class)->record($subscription, [
            'amount' => 99,
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_date' => $paymentDate,
        ], User::factory()->superAdmin()->create());

        $this->assertTrue(
            $result['period_end']->equalTo($existingEnd->copy()->addMonth()),
            'Expected extension from existing period end, got '.$result['period_end']->toDateTimeString(),
        );
    }

    public function test_suspended_subscription_starts_period_from_payment_date(): void
    {
        $subscription = $this->createSubscription(SubscriptionStatus::Suspended, Carbon::parse('2026-01-01'));
        $paymentDate = Carbon::parse('2026-07-10');

        $result = app(ManualPaymentService::class)->record($subscription, [
            'amount' => 50,
            'payment_method' => PaymentMethod::CardManual,
            'reference_note' => 'POS 9988',
            'payment_date' => $paymentDate,
        ], User::factory()->superAdmin()->create());

        $this->assertTrue($result['period_end']->equalTo($paymentDate->copy()->addMonth()));
    }

    public function test_user_without_permission_cannot_authorize_action(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
            'permissions' => [],
        ]);

        $this->assertFalse(Gate::forUser($user)->allows(User::PERMISSION_RECORD_MANUAL_PAYMENT));

        $this->actingAs($user);

        $action = RecordManualPaymentAction::make();
        $this->assertFalse($action->isVisible());
    }

    public function test_billing_staff_with_permission_can_authorize(): void
    {
        $user = User::factory()->withManualPaymentPermission()->create();

        $this->assertTrue(Gate::forUser($user)->allows(User::PERMISSION_RECORD_MANUAL_PAYMENT));

        $this->actingAs($user);
        $this->assertTrue(RecordManualPaymentAction::make()->isVisible());
    }

    public function test_manual_payment_writes_audit_log(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $subscription = $this->createSubscription(SubscriptionStatus::Grace, now()->subDay());

        app(ManualPaymentService::class)->record($subscription, [
            'amount' => 120.5,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::BankTransfer,
            'reference_note' => 'TRX-ABC',
            'payment_date' => now(),
        ], $actor);

        $log = ControlPlaneAuditLog::query()
            ->where('action', 'subscription.manual_payment_recorded')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->actor_id);
        $this->assertTrue($log->high_visibility);
        $this->assertSame(120.5, (float) $log->after['amount']);
        $this->assertSame(PaymentMethod::BankTransfer->value, $log->after['payment_method']);
        $this->assertSame('TRX-ABC', $log->after['reference_note']);
    }

    public function test_stripe_conflict_warning_flag_when_gateway_subscription_present(): void
    {
        $subscription = $this->createSubscription();
        $subscription->update(['payment_gateway_subscription_id' => 'sub_live_abc']);

        $service = app(ManualPaymentService::class);

        $this->assertTrue($service->hasActiveStripeSubscription($subscription));
        $this->assertStringContainsString(
            'does not cancel or interact with Stripe',
            $service->stripeConflictWarningMessage(),
        );

        $result = $service->record($subscription, [
            'amount' => 99,
            'payment_method' => PaymentMethod::Cash,
            'payment_date' => now(),
        ], User::factory()->superAdmin()->create());

        $this->assertTrue($result['stripe_warning']);
        $this->assertTrue(
            ControlPlaneAuditLog::query()
                ->where('action', 'subscription.manual_payment_recorded')
                ->where('after->stripe_subscription_present', true)
                ->exists()
        );
    }

    protected function createSubscription(
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?Carbon $periodEnd = null,
    ): Subscription {
        $church = Church::create([
            'name' => 'Manual Pay Church',
            'subdomain' => 'manual-pay-'.uniqid(),
        ]);

        $plan = Plan::create([
            'name' => 'Standard',
            'price' => 99,
            'billing_interval' => 'monthly',
        ]);

        return Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::FullLock,
            'current_period_end' => $periodEnd ?? now()->addMonth(),
            'grace_started_at' => $status === SubscriptionStatus::Grace ? now()->subDays(2) : null,
        ]);
    }
}
