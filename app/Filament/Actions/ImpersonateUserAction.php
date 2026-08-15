<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\ImpersonationService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class ImpersonateUserAction
{
    public static function make(): Action
    {
        return Action::make('impersonateUser')
            ->label('Impersonate User')
            ->icon('heroicon-o-eye')
            ->schema([
                TextInput::make('target_user_id')
                    ->label('Target user ID (on client instance)')
                    ->numeric()
                    ->required(),
            ])
            ->action(function (Church $record, array $data, ImpersonationService $impersonationService): void {
                $result = $impersonationService->issueToken(
                    church: $record,
                    targetUserId: (int) $data['target_user_id'],
                    actor: auth()->user(),
                );

                Notification::make()
                    ->title('Impersonation token issued')
                    ->body('Correlation: '.$result['correlation_id'])
                    ->success()
                    ->send();
            });
    }
}
