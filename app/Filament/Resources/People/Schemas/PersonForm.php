<?php

namespace App\Filament\Resources\People\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class PersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                Livewire::make(\App\Livewire\CustomFieldsForm::class, fn ($record) => [
                    'entityType' => 'person',
                    'entityId' => $record?->id,
                    'churchId' => $record?->church_id ?? auth()->user()?->church_id,
                ])->key('person-custom-fields'),
            ]);
    }
}
