<?php

namespace App\Filament\Resources\ControlPlaneAuditLogs;

use App\Filament\Resources\ControlPlaneAuditLogs\Pages\ListControlPlaneAuditLogs;
use App\Models\ControlPlaneAuditLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ControlPlaneAuditLogResource extends Resource
{
    protected static ?string $model = ControlPlaneAuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Ops';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('action')->searchable()->sortable(),
                TextColumn::make('actor.name')->label('Actor')->placeholder('System'),
                TextColumn::make('subject_type')->label('Subject'),
                TextColumn::make('subject_id'),
                TextColumn::make('correlation_id')->toggleable(),
                TextColumn::make('high_visibility')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('high_visibility')
                    ->label('High visibility only')
                    ->query(fn (Builder $query): Builder => $query->where('high_visibility', true))
                    ->default(),
                SelectFilter::make('action')
                    ->options(fn () => ControlPlaneAuditLog::query()
                        ->where('high_visibility', true)
                        ->distinct()
                        ->pluck('action', 'action')
                        ->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListControlPlaneAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
