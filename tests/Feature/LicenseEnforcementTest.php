<?php

namespace Tests\Feature;

use App\Models\LicenseCache;
use App\Services\LicenseService;
use Tests\HopeWorksTestCase;

class LicenseEnforcementTest extends HopeWorksTestCase
{
    public function test_suspended_read_only_blocks_non_get_requests(): void
    {
        LicenseCache::query()->delete();
        LicenseCache::query()->create([
            'status' => 'suspended',
            'enforcement_policy' => 'read_only',
        ]);

        $this->get('/test-write-endpoint')->assertOk();

        $this->post('/test-write-endpoint')->assertForbidden();
    }

    public function test_suspended_full_lock_redirects_to_billing_page(): void
    {
        LicenseCache::query()->delete();
        LicenseCache::query()->create([
            'status' => 'suspended',
            'enforcement_policy' => 'full_lock',
        ]);

        $this->get('/test-write-endpoint')
            ->assertRedirect(route('billing.suspended'));
    }

    public function test_active_license_allows_all_requests(): void
    {
        LicenseCache::query()->delete();
        LicenseCache::query()->create([
            'status' => 'active',
            'enforcement_policy' => null,
        ]);

        $this->post('/test-write-endpoint')->assertOk();
    }

    public function test_license_service_stores_payload_from_control_plane(): void
    {
        $service = app(LicenseService::class);

        $service->storeLicensePayload([
            'status' => 'grace',
            'enforcement_policy' => 'banner_only',
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'signed_jwt' => 'test.jwt.token',
        ]);

        $license = $service->current();
        $this->assertEquals('grace', $license->status);
        $this->assertEquals('banner_only', $license->enforcement_policy);
        $this->assertNotNull($license->synced_at);
    }
}
