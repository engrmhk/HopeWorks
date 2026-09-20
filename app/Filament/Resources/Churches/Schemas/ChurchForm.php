<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\ChurchStatus;
use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Synod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Church Details')
                    ->icon('heroicon-o-building-library')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contact_email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('billing_email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Used for Stripe customer + billing notifications; falls back to contact_email then synod contact.'),
                        Select::make('synod_id')
                            ->label('Synod')
                            ->options(fn () => Synod::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Select::make('status')
                            ->options(ChurchStatus::class)
                            ->required()
                            ->default(ChurchStatus::Active)
                            ->helperText('Inactive churches cannot authenticate or receive license updates.'),
                        TextInput::make('subdomain')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Optional override. Leave blank to inherit the parent Synod shared subdomain.'),
                        TextInput::make('custom_domain')
                            ->maxLength(255),
                        TextInput::make('instance_url')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL where the HopeWorks-church instance is deployed.'),
                    ]),
                Section::make('Subscription')
                    ->icon('heroicon-o-receipt-percent')
                    ->iconColor(fn (?Church $record): string => match ($record?->subscription?->status) {
                        SubscriptionStatus::Active => 'success',
                        SubscriptionStatus::Grace => 'warning',
                        SubscriptionStatus::Suspended => 'danger',
                        SubscriptionStatus::Cancelled => 'gray',
                        default => 'gray',
                    })
                    ->description('Status moves active → grace → suspended via the minute scheduler, heartbeat, or Force Status Check. Clients learn changes on their next pull heartbeat.')
                    ->visible(fn (?Church $record): bool => $record !== null)
                    ->columnSpan(1)
                    ->schema([
                        View::make('filament.churches.subscription-overview')
                            ->viewData(fn (?Church $record): array => [
                                'church' => $record,
                                'subscription' => $record?->subscription,
                                'status' => $record?->subscription?->status,
                                'policy' => $record?->subscription?->enforcement_policy,
                            ]),
                    ]),
                Section::make('Church connection')
                    ->icon('heroicon-o-key')
                    ->description('Copy into church Settings → System. Generate the Instance API key here; the JWT secret is shared (Control Plane .env and church System).')
                    ->visible(fn (?Church $record): bool => $record !== null)
                    ->columnSpanFull()
                    ->schema([
                        View::make('filament.churches.instance-connection')
                            ->viewData(function (?Church $record, $livewire): array {
                                return [
                                    'church' => $record,
                                    'revealedApiKey' => $livewire->revealedApiKey ?? null,
                                ];
                            }),
                    ]),
            ]);
    }

    public static function statusBadgeColor(?SubscriptionStatus $status): string
    {
        return match ($status) {
            SubscriptionStatus::Active => 'success',
            SubscriptionStatus::Grace => 'warning',
            SubscriptionStatus::Suspended => 'danger',
            SubscriptionStatus::Cancelled => 'gray',
            default => 'gray',
        };
    }

    public static function policyBadgeColor(?EnforcementPolicy $policy): string
    {
        return match ($policy) {
            EnforcementPolicy::BannerOnly => 'info',
            EnforcementPolicy::ReadOnly => 'warning',
            EnforcementPolicy::FullLock => 'danger',
            default => 'gray',
        };
    }
}
