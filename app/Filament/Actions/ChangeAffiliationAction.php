<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Models\Synod;
use App\Services\AffiliationService;
use App\Services\TenantMigrationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\ValidationException;

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
            ->steps([
                Step::make('Setup')
                    ->description('Choose destination and whether to migrate tenant data.')
                    ->schema([
                        Select::make('synod_id')
                            ->label('New Synod')
                            ->options(fn () => Synod::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('Independent (no synod)')
                            ->visible(fn (Get $get): bool => ! ($get('run_migration') ?? false)),
                        Select::make('destination_church_id')
                            ->label('Destination instance (synod host church)')
                            ->options(fn () => Church::query()->whereNotNull('instance_url')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(fn (Get $get): bool => (bool) ($get('run_migration') ?? false))
                            ->helperText('Required when migrating data. Dry-run import uses commit:false on this instance.'),
                        Toggle::make('run_migration')
                            ->label('Run tenant data migration')
                            ->default(true)
                            ->live(),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->afterValidation(function (Get $get, Set $set, Church $record, TenantMigrationService $tenantMigrationService): void {
                        if (! ($get('run_migration') ?? false)) {
                            $set('dry_run_summary', "Affiliation-only change — no tenant data migration.\nReason: ".$get('reason'));
                            $set('dry_run_token', null);
                            $set('bundle_hash', null);

                            return;
                        }

                        $destinationId = $get('destination_church_id');

                        if (blank($destinationId)) {
                            throw ValidationException::withMessages([
                                'destination_church_id' => 'Destination instance is required for migration.',
                            ]);
                        }

                        $destination = Church::query()->findOrFail($destinationId);
                        $source = self::resolveSourceInstance($record, $destination);

                        $result = $tenantMigrationService->dryRunMigration(
                            church: $record,
                            sourceInstance: $source,
                            destinationInstance: $destination,
                            actor: auth()->user(),
                        );

                        $set('dry_run_token', $result['dry_run_token']);
                        $set('bundle_hash', $result['bundle_hash']);
                        $set('dry_run_summary', self::formatDryRunReport($result));
                    }),
                Step::make('Dry Run')
                    ->description('Review reconciliation before any irreversible commit.')
                    ->schema([
                        Textarea::make('dry_run_summary')
                            ->label('Reconciliation report')
                            ->disabled()
                            ->rows(14)
                            ->dehydrated(),
                        Hidden::make('dry_run_token'),
                        Hidden::make('bundle_hash'),
                    ]),
                Step::make('Confirm')
                    ->description('Live migration is blocked until a matching dry run succeeded.')
                    ->schema([
                        Toggle::make('confirm_live_migration')
                            ->label('I reviewed the dry-run report and want to commit the live migration')
                            ->rules(['accepted'])
                            ->visible(fn (Get $get): bool => (bool) ($get('run_migration') ?? false)),
                        Toggle::make('confirm_affiliation_only')
                            ->label('Confirm affiliation change without migrating tenant data')
                            ->rules(['accepted'])
                            ->visible(fn (Get $get): bool => ! ($get('run_migration') ?? false)),
                    ]),
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
                    $source = self::resolveSourceInstance($record, $destination);

                    $result = $tenantMigrationService->migrateChurch(
                        church: $record,
                        sourceInstance: $source,
                        destinationInstance: $destination,
                        reason: $data['reason'],
                        actor: auth()->user(),
                        dryRunToken: $data['dry_run_token'] ?? null,
                        bundleHash: $data['bundle_hash'] ?? null,
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

    protected static function resolveSourceInstance(Church $record, Church $destination): Church
    {
        return Church::query()
            ->where('instance_url', $record->instance_url)
            ->whereKeyNot($destination->id)
            ->first() ?? $record;
    }

    /**
     * @param  array{correlation_id: string, bundle_hash: string, row_counts: array<string, int>, reconciliation: array<string, mixed>, success: bool}  $result
     */
    protected static function formatDryRunReport(array $result): string
    {
        $lines = [
            'Dry run SUCCEEDED (destination data was not committed).',
            'Correlation: '.$result['correlation_id'],
            'Bundle hash: '.$result['bundle_hash'],
            '',
            'Row counts:',
        ];

        foreach ($result['row_counts'] as $entity => $count) {
            $lines[] = "  - {$entity}: {$count}";
        }

        $integrity = $result['reconciliation']['referential_integrity']
            ?? $result['reconciliation']['integrity']
            ?? null;

        $lines[] = '';
        $lines[] = 'Referential integrity: '.(
            is_array($integrity)
                ? json_encode($integrity, JSON_PRETTY_PRINT)
                : 'not reported by destination (companion repo may add this after T1 fix)'
        );

        $lines[] = '';
        $lines[] = 'Full reconciliation JSON:';
        $lines[] = json_encode($result['reconciliation'], JSON_PRETTY_PRINT);

        return implode("\n", $lines);
    }
}
