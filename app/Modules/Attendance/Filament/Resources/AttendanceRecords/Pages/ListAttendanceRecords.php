<?php

namespace App\Modules\Attendance\Filament\Resources\AttendanceRecords\Pages;

use App\Modules\Attendance\Filament\Resources\AttendanceRecords\AttendanceRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceRecords extends ListRecords
{
    protected static string $resource = AttendanceRecordResource::class;
}
