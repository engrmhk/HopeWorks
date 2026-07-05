<?php

namespace Tests\Feature;

use App\Services\ModuleRegistry;
use Spatie\Permission\Models\Permission;
use Tests\HopeWorksTestCase;

class ModulePermissionTest extends HopeWorksTestCase
{
    public function test_enabling_module_auto_creates_permissions(): void
    {
        $church = $this->createChurch();
        $this->seedChurchModules($church, []);
        $user = $this->createUser($church);
        $this->actingAs($user);

        $this->assertDatabaseMissing('permissions', ['name' => 'attendance.view']);

        app(ModuleRegistry::class)->enableModule('attendance', $church->id);

        $this->assertDatabaseHas('permissions', ['name' => 'attendance.view', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'attendance.manage', 'guard_name' => 'web']);

        $permissions = app(ModuleRegistry::class)->getPermissionsForModule('attendance');
        $this->assertCount(2, $permissions);
        $this->assertInstanceOf(Permission::class, $permissions->first());
    }
}
