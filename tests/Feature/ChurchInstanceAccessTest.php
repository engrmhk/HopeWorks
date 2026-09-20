<?php

namespace Tests\Feature;

use App\Enums\ChurchStatus;
use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Churches\Pages\EditChurch;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Synod;
use App\Models\User;
use App\Services\ChurchInstanceAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChurchInstanceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_and_revoke_api_key(): void
    {
        $church = Church::create([
            'name' => 'Test Church',
            'subdomain' => 'test-church',
        ]);

        $service = app(ChurchInstanceAccessService::class);

        $this->assertFalse($service->hasApiKey($church));

        $plainKey = $service->generateApiKey($church);

        $church->refresh();
        $this->assertTrue($service->hasApiKey($church));
        $this->assertTrue($church->verifyInstanceApiKey($plainKey));
        $this->assertSame($plainKey, $church->instance_api_key);

        $service->revokeApiKey($church);

        $church->refresh();
        $this->assertFalse($service->hasApiKey($church));
        $this->assertFalse($church->verifyInstanceApiKey($plainKey));
        $this->assertNull($church->instance_api_key);
    }

    public function test_rotate_api_key_revokes_existing_licenses(): void
    {
        $synod = Synod::create(['name' => 'Synod']);
        $plan = Plan::create([
            'name' => 'Standard',
            'price' => 99,
            'billing_interval' => 'monthly',
            'module_eligibility' => ['members' => true],
        ]);

        $church = Church::create([
            'synod_id' => $synod->id,
            'name' => 'Rotate Church',
            'subdomain' => 'rotate-church',
        ]);

        $service = app(ChurchInstanceAccessService::class);
        $oldKey = $service->generateApiKey($church);

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::BannerOnly,
            'current_period_end' => now()->addMonth(),
        ]);

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$oldKey,
        ])->assertOk();

        $this->assertDatabaseCount('license_keys', 1);

        $newKey = $service->rotateApiKey($church);

        $this->assertNotSame($oldKey, $newKey);
        $this->assertSame(1, $church->licenseKeys()->whereNotNull('revoked_at')->count());

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$oldKey,
        ])->assertUnauthorized();

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$newKey,
        ])->assertOk();
    }

    public function test_inactive_church_cannot_heartbeat(): void
    {
        $plan = Plan::create([
            'name' => 'Standard',
            'price' => 99,
            'billing_interval' => 'monthly',
            'module_eligibility' => ['members' => true],
        ]);

        $apiKey = 'inactive-church-key';
        $church = Church::create([
            'name' => 'Inactive Church',
            'subdomain' => 'inactive-church',
            'status' => ChurchStatus::Inactive,
            'instance_api_key_hash' => Church::hashApiKey($apiKey),
        ]);

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'grace_period_days' => 7,
            'enforcement_policy' => EnforcementPolicy::FullLock,
            'current_period_end' => now()->addMonth(),
        ]);

        $this->postJson('/api/v1/heartbeat', [], [
            'Authorization' => 'Bearer '.$apiKey,
        ])->assertForbidden()
            ->assertJson([
                'church_status' => ChurchStatus::Inactive->value,
            ]);
    }

    public function test_edit_church_masks_api_key_and_can_reveal_from_storage(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $church = Church::create([
            'name' => 'Key Church',
            'subdomain' => 'key-church',
        ]);

        $plainKey = app(ChurchInstanceAccessService::class)->generateApiKey($church);

        Livewire::actingAs($admin)
            ->test(EditChurch::class, ['record' => $church->getKey()])
            ->assertSuccessful()
            ->assertSet('revealedApiKey', $plainKey)
            ->assertSeeHtml('type="password"')
            ->assertSeeHtml('x-bind:type="showApiKey ? \'text\' : \'password\'"')
            ->assertSee('Instance API key');
    }
}
