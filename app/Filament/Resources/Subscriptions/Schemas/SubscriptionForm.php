<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Synod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class SubscriptionForm
{
    /**
     * @return list<string>
     */
    protected static function commonTimes(): array
    {
        return ['00:00', '06:00', '08:00', '09:00', '12:00', '17:00', '18:00', '23:59'];
    }

    /**
     * Calendar date + simple time, stored as one datetime column.
     *
     * @return array{0: FusedGroup, 1: Hidden}
     */
    protected static function dateAndTime(
        string $name,
        string $label,
        string $helper,
        string $defaultTime,
    ): array {
        $dateField = $name.'_date';
        $timeField = $name.'_time';

        return [
            FusedGroup::make([
                DatePicker::make($dateField)
                    ->hiddenLabel()
                    ->native(false)
                    ->displayFormat('F j, Y')
                    ->placeholder('Pick a date')
                    ->closeOnDateSelection()
                    ->weekStartsOnSunday()
                    ->prefixIcon(Heroicon::CalendarDays)
                    ->dehydrated(false)
                    ->afterStateHydrated(function (DatePicker $component) use ($name): void {
                        $record = $component->getRecord();

                        if (! $record instanceof Subscription || blank($record->{$name})) {
                            return;
                        }

                        $component->state(Carbon::parse($record->{$name})->toDateString());
                    }),
                TimePicker::make($timeField)
                    ->hiddenLabel()
                    ->seconds(false)
                    ->native()
                    ->default($defaultTime)
                    ->placeholder('Time')
                    ->prefixIcon(Heroicon::Clock)
                    ->datalist(self::commonTimes())
                    ->dehydrated(false)
                    ->afterStateHydrated(function (TimePicker $component) use ($name, $defaultTime): void {
                        $record = $component->getRecord();

                        if (! $record instanceof Subscription || blank($record->{$name})) {
                            $component->state($defaultTime);

                            return;
                        }

                        $component->state(Carbon::parse($record->{$name})->format('H:i'));
                    }),
            ])
                ->label($label)
                ->helperText($helper),
            Hidden::make($name)
                ->dehydrateStateUsing(function (Get $get) use ($dateField, $timeField, $defaultTime): ?string {
                    $date = $get($dateField);

                    if (blank($date)) {
                        return null;
                    }

                    $time = $get($timeField) ?: $defaultTime;

                    return Carbon::parse(Carbon::parse($date)->toDateString().' '.$time)
                        ->format('Y-m-d H:i:s');
                }),
        ];
    }

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
                    ->helperText('Days after current period end before the subscription moves from Grace to Suspended.'),
                Select::make('enforcement_policy')
                    ->options(EnforcementPolicy::class)
                    ->required()
                    ->default(EnforcementPolicy::BannerOnly)
                    ->helperText('Controls how HopeWorks-church behaves: Banner Only (warning), Read Only (no writes), Full Lock (blocks access). Sent inside the license JWT on heartbeat.'),
                Section::make('Period dates')
                    ->description('Pick a day from the calendar, then set the time. You can type the time or choose a common one — no seconds.')
                    ->schema([
                        ...self::dateAndTime(
                            name: 'current_period_end',
                            label: 'Current period end',
                            helper: 'Last moment of this billing period. Time defaults to 11:59 PM.',
                            defaultTime: '23:59',
                        ),
                        ...self::dateAndTime(
                            name: 'grace_started_at',
                            label: 'Grace started',
                            helper: 'When the church entered grace. Leave the date empty if they are not in grace. Time defaults to 12:00 AM.',
                            defaultTime: '00:00',
                        ),
                    ]),
                TextInput::make('payment_gateway_customer_id')
                    ->maxLength(255),
            ]);
    }
}
