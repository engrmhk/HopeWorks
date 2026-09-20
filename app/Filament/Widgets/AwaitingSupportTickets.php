<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AwaitingSupportTickets extends TableWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.awaiting-support-tickets';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Support tickets waiting for a reply')
            ->description('New and unanswered messages from church apps.')
            ->query(fn (): Builder => SupportTicket::query()->awaitingStaffReply())
            ->columns([
                TextColumn::make('church.name')
                    ->label('Church')
                    ->placeholder('—'),
                TextColumn::make('subject')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('priority')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('latestMessage.created_at')
                    ->label('Last message')
                    ->since()
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (SupportTicket $record): string => SupportTicketResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('No tickets waiting')
            ->emptyStateDescription('When a church sends a support ticket, it will show up here and in the sidebar.')
            ->emptyStateIcon('heroicon-o-lifebuoy')
            ->paginated(false)
            ->defaultSort('updated_at', 'desc');
    }
}
