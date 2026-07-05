<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                Select::make('billing_interval')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ])
                    ->required()
                    ->default('monthly'),
                TextInput::make('storage_limit_gb')
                    ->numeric()
                    ->required()
                    ->default(10),
                TextInput::make('user_limit')
                    ->numeric()
                    ->required()
                    ->default(25),
                KeyValue::make('module_eligibility')
                    ->label('Module Eligibility')
                    ->keyLabel('Module')
                    ->valueLabel('Enabled')
                    ->addActionLabel('Add module')
                    ->reorderable(),
            ]);
    }
}
