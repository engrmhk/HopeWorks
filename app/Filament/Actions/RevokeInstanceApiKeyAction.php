<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\ChurchInstanceAccessService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RevokeInstanceApiKeyAction
{
    public static function make(): Action
    {
        return Action::make('revokeInstanceApiKey')
            ->label('Revoke API Key')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Church $record): bool => app(ChurchInstanceAccessService::class)->hasApiKey($record))
            ->requiresConfirmation()
            ->modalDescription('This removes the API key and revokes all active license JWTs. The church app will be unable to authenticate until a new key is generated.')
            ->action(function (Church $record, ChurchInstanceAccessService $accessService): void {
                $accessService->revokeApiKey($record);

                Notification::make()
                    ->title('Instance API key revoked')
                    ->body('Heartbeat requests from this church will be rejected until you generate a new key.')
                    ->danger()
                    ->send();
            });
    }
}
