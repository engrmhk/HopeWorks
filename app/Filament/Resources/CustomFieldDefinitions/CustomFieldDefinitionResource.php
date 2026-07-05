<?php

namespace App\Filament\Resources\CustomFieldDefinitions;

use App\Filament\Resources\CustomFieldDefinitions\Pages\CreateCustomFieldDefinition;
use App\Filament\Resources\CustomFieldDefinitions\Pages\EditCustomFieldDefinition;
use App\Filament\Resources\CustomFieldDefinitions\Pages\ListCustomFieldDefinitions;
use App\Filament\Resources\CustomFieldDefinitions\Schemas\CustomFieldDefinitionForm;
use App\Filament\Resources\CustomFieldDefinitions\Tables\CustomFieldDefinitionsTable;
use App\Models\CustomFieldDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomFieldDefinitionResource extends Resource
{
    protected static ?string $model = CustomFieldDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Custom Fields';

    public static function form(Schema $schema): Schema
    {
        return CustomFieldDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomFieldDefinitionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomFieldDefinitions::route('/'),
            'create' => CreateCustomFieldDefinition::route('/create'),
            'edit' => EditCustomFieldDefinition::route('/{record}/edit'),
        ];
    }
}
