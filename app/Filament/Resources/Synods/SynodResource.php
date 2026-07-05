<?php

namespace App\Filament\Resources\Synods;

use App\Filament\Resources\Synods\Pages\CreateSynod;
use App\Filament\Resources\Synods\Pages\EditSynod;
use App\Filament\Resources\Synods\Pages\ListSynods;
use App\Filament\Resources\Synods\Schemas\SynodForm;
use App\Filament\Resources\Synods\Tables\SynodsTable;
use App\Models\Synod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SynodResource extends Resource
{
    protected static ?string $model = Synod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Tenants';

    public static function form(Schema $schema): Schema
    {
        return SynodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SynodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSynods::route('/'),
            'create' => CreateSynod::route('/create'),
            'edit' => EditSynod::route('/{record}/edit'),
        ];
    }
}
