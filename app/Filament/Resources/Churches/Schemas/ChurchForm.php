<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\ChurchStatus;
use App\Models\Church;
use App\Models\Synod;
use App\Services\ChurchInstanceAccessService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Church Details')
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
                Section::make('Instance Access')
                    ->description('HopeWorks-church polls this control plane via POST /api/v1/heartbeat using the instance API key. Subscription status and enforcement policy are delivered inside the signed license JWT — there is no push notification.')
                    ->visible(fn (?Church $record): bool => $record !== null)
                    ->schema([
                        TextEntry::make('api_key_status')
                            ->label('Instance API Key')
                            ->state(fn (?Church $record): string => $record && app(ChurchInstanceAccessService::class)->hasApiKey($record)
                                ? 'Configured (plain key is only shown when generated or rotated)'
                                : 'Not configured — generate an API key using the header actions'),
                        TextEntry::make('subscription_status')
                            ->label('Subscription Status')
                            ->state(fn (?Church $record): string => $record?->subscription?->status?->label() ?? 'No subscription — create one under Billing'),
                        TextEntry::make('enforcement_policy')
                            ->label('Enforcement Policy')
                            ->state(fn (?Church $record): string => $record?->subscription?->enforcement_policy?->label() ?? '—'),
                        TextEntry::make('last_license_issued')
                            ->label('Last License Issued')
                            ->state(fn (?Church $record): string => optional($record?->licenseKeys()->latest('issued_at')->first()?->issued_at)?->toDateTimeString() ?? 'Never'),
                    ]),
            ]);
    }
}
