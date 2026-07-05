<?php

namespace App\Filament\Resources\CustomFieldDefinitions\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomFieldDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('entity_type')
                    ->options([
                        'person' => 'Person',
                    ])
                    ->required(),
                TextInput::make('field_key')
                    ->required()
                    ->alphaDash()
                    ->maxLength(255),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Select::make('field_type')
                    ->options([
                        'text' => 'Text',
                        'textarea' => 'Textarea',
                        'number' => 'Number',
                        'select' => 'Select',
                    ])
                    ->required()
                    ->live(),
                TagsInput::make('options')
                    ->visible(fn ($get) => $get('field_type') === 'select'),
                TextInput::make('validation_rules')
                    ->helperText('Comma-separated Laravel validation rules, e.g. max:255,email')
                    ->dehydrateStateUsing(fn (?string $state) => $state ? array_map('trim', explode(',', $state)) : [])
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(',', $state) : $state),
                Checkbox::make('is_required'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
