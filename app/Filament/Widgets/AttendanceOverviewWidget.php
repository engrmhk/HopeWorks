<?php

namespace App\Filament\Widgets;

use App\Services\ModuleRegistry;
use Filament\Widgets\Widget;

class AttendanceOverviewWidget extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.attendance-overview';

    protected static ?string $moduleKey = 'attendance';

    public static function canView(): bool
    {
        return app(ModuleRegistry::class)->isEnabled(static::$moduleKey ?? 'attendance');
    }
}
