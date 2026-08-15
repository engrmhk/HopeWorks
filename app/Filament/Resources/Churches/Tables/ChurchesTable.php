<?php

namespace App\Filament\Resources\Churches\Tables;

use App\Filament\Actions\ChangeAffiliationAction;
use App\Filament\Actions\ProvisionNewClientAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChurchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('synod.name')
                    ->label('Synod')
                    ->sortable()
                    ->placeholder('Independent'),
                TextColumn::make('subdomain')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('subscription.status')
                    ->label('Subscription')
                    ->badge()
                    ->placeholder('None'),
                TextColumn::make('instance_api_key_hash')
                    ->label('API Key')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Configured' : 'Missing')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                ChangeAffiliationAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
