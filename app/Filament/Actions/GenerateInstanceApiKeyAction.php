<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\ChurchInstanceAccessService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class GenerateInstanceApiKeyAction
{
    public static function make(): Action
    {
        return Action::make('generateInstanceApiKey')
            ->label('Generate API Key')
            ->icon('heroicon-o-key')
            ->color('success')
            ->visible(fn (Church $record): bool => ! app(ChurchInstanceAccessService::class)->hasApiKey($record))
            ->modalHeading('Generate instance API key')
            ->modalDescription('This creates a one-time key for Settings → System → Control Plane connection on the church app. Copy it in the next step — it cannot be shown again.')
            ->modalSubmitActionLabel('Generate key')
            ->successNotification(null)
            ->action(function (Church $record, ChurchInstanceAccessService $accessService, $livewire): void {
                $plainKey = $accessService->generateApiKey($record);

                if (method_exists($livewire, 'revealInstanceConnection')) {
                    $livewire->revealInstanceConnection($plainKey);

                    return;
                }

                Notification::make()
                    ->title('Instance API key generated')
                    ->body($plainKey)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
