<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Models\Synod;
use App\Services\AffiliationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ChangeAffiliationAction
{
    public static function make(): Action
    {
        return Action::make('changeAffiliation')
            ->label('Change Affiliation')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Select::make('synod_id')
                    ->label('New Synod')
                    ->options(fn () => Synod::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->placeholder('Independent (no synod)'),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Church $record, array $data, AffiliationService $affiliationService): void {
                $affiliationService->changeAffiliation(
                    church: $record,
                    newSynodId: $data['synod_id'] ?? null,
                    reason: $data['reason'],
                    actor: auth()->user(),
                );

                Notification::make()
                    ->title('Affiliation updated')
                    ->success()
                    ->send();
            });
    }
}
