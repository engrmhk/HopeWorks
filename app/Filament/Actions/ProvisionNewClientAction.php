<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\TenantProvisioningService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class ProvisionNewClientAction
{
    public static function make(): Action
    {
        return Action::make('provisionNewClient')
            ->label('Provision New Client')
            ->icon('heroicon-o-server-stack')
            ->schema([
                TextInput::make('name')
                    ->label('Church Name')
                    ->required()
                    ->maxLength(255),
                Select::make('synod_id')
                    ->label('Existing Synod')
                    ->options(fn () => \App\Models\Synod::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('synod_name')
                    ->label('Or Create New Synod')
                    ->maxLength(255)
                    ->helperText('Used when no existing synod is selected.'),
            ])
            ->action(function (array $data, TenantProvisioningService $provisioningService): void {
                $result = $provisioningService->provisionNewClient($data);
                $church = $result['church'];
                $apiKey = $result['api_key'];

                Notification::make()
                    ->title('Client provisioned')
                    ->body("Subdomain: {$church->subdomain}\n\nInstance API key (copy to church .env):\n{$apiKey}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
