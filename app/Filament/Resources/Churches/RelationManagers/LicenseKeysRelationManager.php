<?php

namespace App\Filament\Resources\Churches\RelationManagers;

use App\Models\LicenseKey;
use App\Services\ChurchInstanceAccessService;
use App\Services\LicenseKeyService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class LicenseKeysRelationManager extends RelationManager
{
    protected static string $relationship = 'licenseKeys';

    protected static ?string $title = 'License Keys';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('revoked_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('display_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (LicenseKey $record): string => $record->displayStatus())
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'warning',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('revokeAllActive')
                    ->label('Revoke All Active')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Revoke every non-expired license JWT for this church. The church app will enforce restrictions on its next heartbeat or when the cached license expires.')
                    ->action(function (): void {
                        $church = $this->getOwnerRecord();
                        $count = app(LicenseKeyService::class)->revokeAllForChurch($church);

                        Notification::make()
                            ->title('Active licenses revoked')
                            ->body("{$count} license JWT(s) revoked. The church app will pick this up on its next heartbeat.")
                            ->warning()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LicenseKey $record): bool => $record->revoked_at === null)
                    ->requiresConfirmation()
                    ->action(function (LicenseKey $record, ChurchInstanceAccessService $accessService): void {
                        $accessService->revokeLicenseKey($record);

                        Notification::make()
                            ->title('License key revoked')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
