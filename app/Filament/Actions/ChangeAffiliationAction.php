<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Models\Synod;
use App\Services\AffiliationService;
use App\Services\TenantMigrationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

class ChangeAffiliationAction
{
    public const DISABLED_TOOLTIP = 'Temporarily disabled — data migration safety fix in progress.';

    public static function make(): Action
    {
        $enabled = (bool) config('hopeworks.tenant_migration.affiliation_change_enabled', false);

        return Action::make('changeAffiliation')
            ->label('Change Affiliation')
            ->icon('heroicon-o-arrows-right-left')
            ->disabled(! $enabled)
            ->tooltip($enabled ? null : self::DISABLED_TOOLTIP)
            ->schema([
                Select::make('synod_id')
                    ->label('New Synod')
                    ->options(fn () => Synod::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->placeholder('Independent (no synod)'),
                Select::make('destination_church_id')
                    ->label('Destination instance (synod host church)')
                    ->options(fn () => Church::query()->whereNotNull('instance_url')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->helperText('Required when migrating data between client instances.'),
                Toggle::make('run_migration')
                    ->label('Run tenant data migration')
                    ->default(true),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (
                Church $record,
                array $data,
                AffiliationService $affiliationService,
                TenantMigrationService $tenantMigrationService,
            ): void {
                if (! config('hopeworks.tenant_migration.affiliation_change_enabled', false)) {
                    Notification::make()
                        ->title('Change Affiliation is disabled')
                        ->body(self::DISABLED_TOOLTIP)
                        ->danger()
                        ->send();

                    return;
                }

                if (($data['run_migration'] ?? false) && ! empty($data['destination_church_id'])) {
                    $destination = Church::query()->findOrFail($data['destination_church_id']);
                    $source = Church::query()
                        ->where('instance_url', $record->instance_url)
                        ->whereKeyNot($destination->id)
                        ->first() ?? $record;

                    $result = $tenantMigrationService->migrateChurch(
                        church: $record,
                        sourceInstance: $source,
                        destinationInstance: $destination,
                        reason: $data['reason'],
                        actor: auth()->user(),
                    );

                    Notification::make()
                        ->title('Affiliation changed with migration')
                        ->body('Correlation: '.$result['correlation_id'])
                        ->success()
                        ->send();

                    return;
                }

                $affiliationService->changeAffiliation(
                    church: $record,
                    newSynodId: $data['synod_id'] ?? null,
                    reason: $data['reason'],
                    actor: auth()->user(),
                );

                Notification::make()
                    ->title('Affiliation updated')
                    ->success()
                    ->send();
            });
    }
}
