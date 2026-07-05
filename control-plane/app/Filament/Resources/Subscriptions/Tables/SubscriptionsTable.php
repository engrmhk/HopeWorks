<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('church.name')
                    ->label('Church')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('synod.name')
                    ->label('Synod')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('enforcement_policy')
                    ->badge(),
                TextColumn::make('current_period_end')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('grace_started_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
