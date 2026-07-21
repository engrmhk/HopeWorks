<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportTicketNotificationService;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Message Thread';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                IconColumn::make('is_staff')
                    ->label('Staff')
                    ->boolean(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->placeholder('Church'),
                TextColumn::make('body')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Reply')
                    ->schema([
                        Textarea::make('body')
                            ->label('Reply')
                            ->required()
                            ->rows(5),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = auth()->id();
                        $data['is_staff'] = true;

                        return $data;
                    })
                    ->after(function (SupportTicketMessage $record, SupportTicketNotificationService $notifications): void {
                        /** @var SupportTicket $ticket */
                        $ticket = $this->getOwnerRecord();

                        if ($ticket->status === SupportTicketStatus::Open) {
                            $ticket->update(['status' => SupportTicketStatus::InProgress]);
                        }

                        $notifications->notifyChurchOfStaffReply($ticket, $record);

                        Notification::make()
                            ->title('Reply sent')
                            ->body('The church contact was notified via the Control Plane messenger.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
