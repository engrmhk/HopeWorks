<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\ChurchStatus;
use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Synod;
use App\Services\ChurchInstanceAccessService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
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
                Section::make('Instance Access')
                    ->icon('heroicon-o-key')
                    ->description('HopeWorks-church polls this control plane via POST /api/v1/heartbeat using the instance API key. Status and enforcement are delivered in the signed license JWT — there is no push.')
                    ->visible(fn (?Church $record): bool => $record !== null)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('api_key_status')
                                    ->label('Instance API Key')
                                    ->badge()
                                    ->color(fn (?Church $record): string => $record && app(ChurchInstanceAccessService::class)->hasApiKey($record)
                                        ? 'success'
                                        : 'danger')
                                    ->state(fn (?Church $record): string => $record && app(ChurchInstanceAccessService::class)->hasApiKey($record)
                                        ? 'Configured'
                                        : 'Not configured')
                                    ->helperText(fn (?Church $record): string => $record && app(ChurchInstanceAccessService::class)->hasApiKey($record)
                                        ? 'Plain key is only shown when generated or rotated.'
                                        : 'Generate an API key from the API Key header menu.'),
                                TextEntry::make('last_license_issued')
                                    ->label('Last License Issued')
                                    ->icon('heroicon-m-clock')
                                    ->state(fn (?Church $record): string => optional($record?->licenseKeys()->latest('issued_at')->first()?->issued_at)?->toDateTimeString() ?? 'Never'),
                            ]),
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
