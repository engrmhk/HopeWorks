<?php

namespace App\Filament\Resources\SupportTickets;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Filament\Resources\SupportTickets\Pages\CreateSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Models\SupportTicket;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Ops';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('church_id')
                ->relationship('church', 'name')
                ->searchable(),
            TextInput::make('subject')->required()->maxLength(255),
            Select::make('status')
                ->options(collect(SupportTicketStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name]))
                ->required(),
            Select::make('priority')
                ->options(collect(SupportTicketPriority::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name]))
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('church.name')->label('Church'),
                TextColumn::make('subject')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('priority')->badge(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'create' => CreateSupportTicket::route('/create'),
            'edit' => EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
