<?php

namespace App\Modules\Attendance\Filament\Resources\AttendanceRecords;

use App\Modules\Attendance\Filament\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Services\ModuleRegistry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceRecordResource extends Resource
{
    protected static ?string $model = AttendanceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Attendance Records';

    protected static ?string $moduleKey = 'attendance';

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleRegistry::class)->isEnabled(static::$moduleKey ?? 'attendance');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('notes')
                    ->limit(50),
            ])
            ->filters([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceRecords::route('/'),
        ];
    }
}
