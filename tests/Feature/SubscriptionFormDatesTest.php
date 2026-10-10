<?php

namespace Tests\Feature;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\EditSubscription;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionFormDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_dates_use_calendar_and_simple_time_instead_of_datetime_local(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $subscription = $this->makeSubscription();

        Livewire::actingAs($admin)
            ->test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->assertSuccessful()
            ->assertSee('Current period end')
            ->assertSee('Grace started')
            ->assertSee('Pick a date')
            ->assertDontSeeHtml('type="datetime-local"');
    }

    public function test_saving_date_and_time_writes_one_datetime(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $subscription = $this->makeSubscription();

        Livewire::actingAs($admin)
            ->test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->fillForm([
                'current_period_end_date' => '2026-11-15',
                'current_period_end_time' => '23:59',
                'grace_started_at_date' => '2026-10-10',
                'grace_started_at_time' => '08:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $subscription->refresh();

        $this->assertSame('2026-11-15 23:59:00', $subscription->current_period_end?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-10 08:00:00', $subscription->grace_started_at?->format('Y-m-d H:i:s'));
    }

    public function test_clearing_grace_date_stores_null(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $subscription = $this->makeSubscription([
            'status' => SubscriptionStatus::Grace,
            'grace_started_at' => now()->subDays(2),
        ]);

        Livewire::actingAs($admin)
            ->test(EditSubscription::class, ['record' => $subscription->getKey()])
            ->fillForm([
                'grace_started_at_date' => null,
                'grace_started_at_time' => '00:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($subscription->fresh()->grace_started_at);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSubscription(array $overrides = []): Subscription
    {
        $plan = Plan::create([
            'name' => 'Pro',
            'price' => 149,
            'billing_interval' => 'monthly',
            'module_eligibility' => ['members' => true],
        ]);

        $church = Church::create([
            'name' => 'Date Church',
            'subdomain' => 'date-church',
        ]);

        return Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::BannerOnly,
            'current_period_end' => now()->addMonth()->setTime(13, 10, 10),
            ...$overrides,
        ]);
    }
}
