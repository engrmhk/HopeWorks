<?php

namespace App\Filament\Pages;

use App\Models\Church;
use App\Services\TenantHealthService;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantHealthDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Ops';

    protected static ?string $navigationLabel = 'Tenant Health';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.tenant-health-dashboard';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Church::query()->with(['latestHealthSnapshot', 'subscription.plan'])
            )
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('latestHealthSnapshot.app_tag')->label('App tag'),
                TextColumn::make('latestHealthSnapshot.app_version')->label('Version'),
                TextColumn::make('latestHealthSnapshot.active_user_count')->label('Users'),
                TextColumn::make('latestHealthSnapshot.storage_bytes')
                    ->label('Storage')
                    ->formatStateUsing(fn (?int $state, Church $record): string => $this->formatStoragePercent($state, $record)),
                TextColumn::make('latestHealthSnapshot.last_login_at')->label('Last login')->dateTime(),
                IconColumn::make('at_risk')
                    ->label('At risk')
                    ->state(fn (Church $record): bool => app(TenantHealthService::class)->isAtRisk($record->latestHealthSnapshot))
                    ->boolean(),
            ]);
    }

    protected function formatStoragePercent(?int $bytes, Church $record): string
    {
        $limitGb = $record->subscription?->plan?->storage_limit_gb ?? 0;

        if ($bytes === null || $limitGb <= 0) {
            return '—';
        }

        $pct = round(($bytes / ($limitGb * 1024 * 1024 * 1024)) * 100, 1);

        return $pct.'%';
    }
}
