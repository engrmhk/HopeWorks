<?php

namespace App\Filament\Resources\Synods\Schemas;

use App\Enums\SynodStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SynodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('region')
                    ->maxLength(255),
                Select::make('status')
                    ->options(SynodStatus::class)
                    ->required()
                    ->default(SynodStatus::Active),
                TextInput::make('contact_name')
                    ->maxLength(255),
                TextInput::make('contact_email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('contact_phone')
                    ->tel()
                    ->maxLength(255),
            ]);
    }
}
