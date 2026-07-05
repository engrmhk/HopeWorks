<?php

namespace Tests\Feature;

use App\Filament\Widgets\AttendanceOverviewWidget;
use App\Models\LicenseCache;
use App\Modules\Attendance\Filament\Resources\AttendanceRecords\AttendanceRecordResource;
use App\Services\ModuleRegistry;
use Tests\HopeWorksTestCase;

class ModuleRegistryTest extends HopeWorksTestCase
{
    public function test_disabling_module_hides_navigation(): void
    {
        $church = $this->createChurch();
        $this->seedChurchModules($church, ['attendance']);
        $user = $this->createUser($church);
        $this->actingAs($user);

        app(ModuleRegistry::class)->enableModule('attendance', $church->id);
        $this->assertTrue(AttendanceRecordResource::shouldRegisterNavigation());

        app(ModuleRegistry::class)->disableModule('attendance', $church->id);
        $this->assertFalse(AttendanceRecordResource::shouldRegisterNavigation());
    }

    public function test_disabling_module_blocks_routes_with_404(): void
    {
        $church = $this->createChurch();
        $this->seedChurchModules($church, []);
        $user = $this->createUser($church);
        $this->actingAs($user);

        $this->get(route('attendance.index'))->assertNotFound();

        app(ModuleRegistry::class)->enableModule('attendance', $church->id);

        $this->get(route('attendance.index'))->assertOk();
    }

    public function test_disabling_module_hides_widgets(): void
    {
        $church = $this->createChurch();
        $this->seedChurchModules($church, ['attendance']);
        $user = $this->createUser($church);
        $this->actingAs($user);

        app(ModuleRegistry::class)->enableModule('attendance', $church->id);
        $this->assertTrue(AttendanceOverviewWidget::canView());

        app(ModuleRegistry::class)->disableModule('attendance', $church->id);
        $this->assertFalse(AttendanceOverviewWidget::canView());
    }
}
