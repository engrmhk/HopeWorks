<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\ChurchInstanceAccessService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RotateInstanceApiKeyAction
{
    public static function make(): Action
    {
        return Action::make('rotateInstanceApiKey')
            ->label('Rotate API Key')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Church $record): bool => app(ChurchInstanceAccessService::class)->hasApiKey($record))
            ->requiresConfirmation()
            ->modalDescription('This invalidates the current API key, revokes all active license JWTs, and issues a new key. The church app will stop syncing until you update its .env.')
            ->action(function (Church $record, ChurchInstanceAccessService $accessService): void {
                $plainKey = $accessService->rotateApiKey($record);

                Notification::make()
                    ->title('Instance API key rotated')
                    ->body("Update the church app's CONTROL_PLANE_API_KEY:\n\n{$plainKey}")
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }
}
