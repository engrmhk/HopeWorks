<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\ChurchStatus;
use App\Models\Synod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('synod_id')
                    ->label('Synod')
                    ->options(fn () => Synod::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('status')
                    ->options(ChurchStatus::class)
                    ->required()
                    ->default(ChurchStatus::Active),
                TextInput::make('subdomain')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('custom_domain')
                    ->maxLength(255),
                TextInput::make('instance_url')
                    ->url()
                    ->maxLength(255),
            ]);
    }
}
