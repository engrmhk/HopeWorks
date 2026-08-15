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
            ->requiresConfirmation()
            ->modalDescription('Generate an API key for this church. The HopeWorks-church instance uses it as a Bearer token when calling /api/v1/heartbeat. Copy the key now — it cannot be shown again.')
            ->action(function (Church $record, ChurchInstanceAccessService $accessService): void {
                $plainKey = $accessService->generateApiKey($record);

                Notification::make()
                    ->title('Instance API key generated')
                    ->body("Copy this key into the church app's .env as CONTROL_PLANE_API_KEY:\n\n{$plainKey}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
