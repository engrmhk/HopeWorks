<?php

namespace Tests\Feature;

use App\Exceptions\LicenseJwtSecretTooShortException;
use App\Filament\Pages\LicenseConnectionSettings;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\LicenseJwtSecret;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class LicenseJwtSecretTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(LicenseJwtSecret::CACHE_KEY);
        PlatformSetting::query()->delete();
        config(['license.jwt_secret' => '']);
    }

    public function test_generate_stores_secret_in_database(): void
    {
        $this->assertFalse(LicenseJwtSecret::isReady());

        $secret = LicenseJwtSecret::generate();

        $this->assertGreaterThanOrEqual(32, strlen($secret));
        $this->assertSame($secret, LicenseJwtSecret::configured());
        $this->assertSame($secret, PlatformSetting::current()->fresh()->license_jwt_secret);
        $this->assertSame($secret, LicenseJwtSecret::signingKey());
    }

    public function test_save_rejects_short_secret(): void
    {
        $this->expectException(LicenseJwtSecretTooShortException::class);
        LicenseJwtSecret::save('too-short');
    }

    public function test_license_connection_page_generates_and_saves_jwt_secret(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $component = Livewire::actingAs($admin)
            ->test(LicenseConnectionSettings::class)
            ->assertSuccessful()
            ->call('generateLicenseJwtSecret');

        $generated = $component->get('jwtSecretInput');

        $this->assertGreaterThanOrEqual(32, strlen($generated));
        $this->assertSame($generated, LicenseJwtSecret::configured());

        $pasted = str_repeat('b', 40);

        Livewire::actingAs($admin)
            ->test(LicenseConnectionSettings::class)
            ->set('jwtSecretInput', $pasted)
            ->call('saveLicenseJwtSecret')
            ->assertSet('jwtSecretInput', $pasted);

        $this->assertSame($pasted, LicenseJwtSecret::configured());
        $this->assertSame($pasted, PlatformSetting::current()->fresh()->license_jwt_secret);
    }
}
