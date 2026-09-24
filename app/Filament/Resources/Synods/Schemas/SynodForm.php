<?php

namespace App\Filament\Resources\Synods\Schemas;

use App\Enums\EnforcementPolicy;
use App\Enums\NoticeSeverity;
use App\Enums\SynodStatus;
use App\Models\Synod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class SynodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Synod details')
                    ->description('Free account — no subscription. Member churches keep their own billing.')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('shared_subdomain')
                            ->label('Shared subdomain')
                            ->maxLength(255)
                            ->helperText('Synod host on the church app (e.g. synod.example.com).'),
                        TextInput::make('instance_url')
                            ->label('Church app URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Same HopeWorks-church deploy URL used by member churches.'),
                        TextInput::make('region')
                            ->maxLength(255),
                        TextInput::make('contact_name')
                            ->maxLength(255),
                        TextInput::make('contact_email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->tel()
                            ->maxLength(255),
                    ]),
                Section::make('Access & banner')
                    ->description('Applies to every church under this synod on the next Sync Now / heartbeat.')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')
                            ->options(SynodStatus::class)
                            ->required()
                            ->default(SynodStatus::Active)
                            ->helperText('Inactive locks the synod and all member churches (full lock + banner).'),
                        Select::make('enforcement_policy')
                            ->label('Banner / lock')
                            ->options(EnforcementPolicy::class)
                            ->placeholder('None (use each church subscription)')
                            ->helperText('Optional override. Combined with each church’s own policy — the stricter one wins.'),
                        Select::make('notice_severity')
                            ->options(NoticeSeverity::class)
                            ->default(NoticeSeverity::Warning),
                        Textarea::make('platform_notice')
                            ->label('Notification / banner')
                            ->rows(4)
                            ->helperText('Shown on the synod and every church under it. Leave empty for no extra message.'),
                    ]),
                Section::make('Synod connection')
                    ->description('Paste into the church app Settings → System for the synod host. JWT is the same shared secret as churches.')
                    ->visible(fn (?Synod $record): bool => $record !== null)
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        View::make('filament.synods.instance-connection')
                            ->columnSpanFull()
                            ->viewData(function (?Synod $record, $livewire): array {
                                return [
                                    'synod' => $record,
                                    'revealedApiKey' => $livewire->revealedApiKey ?? null,
                                ];
                            }),
                    ]),
            ]);
    }
}
