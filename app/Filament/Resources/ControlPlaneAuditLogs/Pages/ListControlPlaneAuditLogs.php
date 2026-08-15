<?php

namespace App\Filament\Resources\ControlPlaneAuditLogs\Pages;

use App\Filament\Resources\ControlPlaneAuditLogs\ControlPlaneAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListControlPlaneAuditLogs extends ListRecords
{
    protected static string $resource = ControlPlaneAuditLogResource::class;
}
