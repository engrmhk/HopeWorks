<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Synod;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('church_id')
                    ->label('Church')
                    ->options(fn () => Church::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('synod_id')
                    ->label('Synod')
                    ->options(fn () => Synod::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn () => Plan::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Select::make('status')
                    ->options(SubscriptionStatus::class)
                    ->required()
                    ->default(SubscriptionStatus::Active)
                    ->helperText('Client apps learn about status changes on their next heartbeat — not instantly. Active→Grace→Suspended is evaluated every minute by subscriptions:evaluate-statuses (also on heartbeat / Force Status Check).'),
                TextInput::make('grace_period_days')
                    ->numeric()
                    ->required()
                    ->default(7)
                    ->helperText('Days after current_period_end before the subscription moves from Grace to Suspended.'),
                Select::make('enforcement_policy')
                    ->options(EnforcementPolicy::class)
                    ->required()
                    ->default(EnforcementPolicy::BannerOnly)
                    ->helperText('Controls how HopeWorks-church behaves: Banner Only (warning), Read Only (no writes), Full Lock (blocks access). Sent inside the license JWT on heartbeat.'),
                DateTimePicker::make('current_period_end'),
                DateTimePicker::make('grace_started_at'),
                TextInput::make('payment_gateway_customer_id')
                    ->maxLength(255),
            ]);
    }
}
