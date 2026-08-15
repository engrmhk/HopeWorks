<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\SubscriptionEnforcementService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ForceStatusCheckAction
{
    public static function make(): Action
    {
        return Action::make('forceStatusCheck')
            ->label('Force Status Check')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (?Church $record): bool => $record?->subscription !== null)
            ->requiresConfirmation()
            ->modalDescription('Runs the same active→grace→suspended evaluation as the every-minute scheduler (subscriptions:evaluate-statuses), immediately for this church.')
            ->action(function (Church $record, SubscriptionEnforcementService $enforcementService): void {
                $before = $record->subscription?->status;
                $after = $enforcementService->forceStatusCheckForChurch($record, auth()->user());

                if ($after === null) {
                    Notification::make()
                        ->title('No subscription')
                        ->danger()
                        ->send();

                    return;
                }

                $changed = $before !== null && $before !== $after->status;

                Notification::make()
                    ->title($changed ? 'Status transitioned' : 'Status checked (no change)')
                    ->body(sprintf(
                        'Status: %s → %s. Last evaluated: %s. Core App learns this on its next heartbeat (or after license invalidation).',
                        $before?->label() ?? '—',
                        $after->status->label(),
                        $after->status_evaluated_at?->toDateTimeString() ?? '—',
                    ))
                    ->success()
                    ->send();
            });
    }
}
