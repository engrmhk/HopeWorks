<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\TenantOffboardingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ExportOffboardingAction
{
    public static function make(): Action
    {
        return Action::make('exportOffboarding')
            ->label('Export for Offboarding')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->requiresConfirmation()
            ->action(function (Church $record, TenantOffboardingService $service): void {
                $job = $service->exportChurch($record, auth()->user());

                Notification::make()
                    ->title('Offboarding export queued')
                    ->body('Job #'.$job->id)
                    ->success()
                    ->send();
            });
    }
}
