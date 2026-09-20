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
            ->modalHeading('Rotate instance API key')
            ->modalDescription('The current key stops working immediately. Paste the new key into Settings → System → Control Plane connection on the church app. Active license JWTs are revoked.')
            ->modalSubmitActionLabel('Rotate key')
            ->requiresConfirmation()
            ->successNotification(null)
            ->action(function (Church $record, ChurchInstanceAccessService $accessService, $livewire): void {
                $plainKey = $accessService->rotateApiKey($record);

                if (method_exists($livewire, 'revealInstanceConnection')) {
                    $livewire->revealInstanceConnection($plainKey);

                    return;
                }

                Notification::make()
                    ->title('Instance API key rotated')
                    ->body($plainKey)
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }
}
