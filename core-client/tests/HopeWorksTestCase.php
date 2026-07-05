<?php

namespace Tests;

use App\Models\Church;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

abstract class HopeWorksTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Synod Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Church Admin', 'guard_name' => 'web']);

        app(ModuleRegistry::class)->syncManifestsToDatabase();
    }

    protected function createChurch(array $attributes = []): Church
    {
        return Church::factory()->create($attributes);
    }

    protected function createUser(Church $church, string $role = 'Church Admin', array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'church_id' => $church->id,
        ], $attributes));

        $user->assignRole($role);

        return $user;
    }

    protected function seedChurchModules(Church $church, array $enabled = []): void
    {
        app(ModuleRegistry::class)->seedChurchModules($church, $enabled);
    }
}
