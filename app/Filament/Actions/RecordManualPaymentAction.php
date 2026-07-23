<?php

namespace App\Filament\Actions;

use App\Enums\PaymentMethod;
use App\Models\Church;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ManualPaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RecordManualPaymentAction
{
    public static function make(): Action
    {
        return Action::make('recordManualPayment')
            ->label('Record Manual Payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->authorize(User::PERMISSION_RECORD_MANUAL_PAYMENT)
            ->visible(fn (): bool => (bool) auth()->user()?->can(User::PERMISSION_RECORD_MANUAL_PAYMENT))
            ->disabled(fn (?Model $record): bool => self::resolveSubscription($record) === null)
            ->schema(function (?Model $record): array {
                $subscription = self::resolveSubscription($record);
                $stripeWarning = $subscription
                    && app(ManualPaymentService::class)->hasActiveStripeSubscription($subscription);

                return [
                    Placeholder::make('stripe_conflict_warning')
                        ->label('Stripe conflict')
                        ->content(fn (): string => app(ManualPaymentService::class)->stripeConflictWarningMessage())
                        ->visible($stripeWarning),
                    TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->prefix(config('hopeworks.billing.currency', 'USD')),
                    TextInput::make('currency')
                        ->default(config('hopeworks.billing.currency', 'USD'))
                        ->required()
                        ->maxLength(3),
                    Select::make('payment_method')
                        ->label('Payment method')
                        ->options([
                            PaymentMethod::Cash->value => PaymentMethod::Cash->label(),
                            PaymentMethod::BankTransfer->value => PaymentMethod::BankTransfer->label(),
                            PaymentMethod::CardManual->value => PaymentMethod::CardManual->label(),
                        ])
                        ->required()
                        ->helperText('card_manual = charged outside Hope Works (POS/phone). Never enter raw card numbers.'),
                    Textarea::make('reference_note')
                        ->label('Reference note')
                        ->rows(2)
                        ->helperText('Cheque number, bank reference, POS receipt ID, etc.'),
                    DatePicker::make('payment_date')
                        ->label('Payment date')
                        ->required()
                        ->default(now()),
                ];
            })
            ->requiresConfirmation()
            ->modalDescription(function (?Model $record): string {
                $subscription = self::resolveSubscription($record);
                $service = app(ManualPaymentService::class);

                if ($subscription && $service->hasActiveStripeSubscription($subscription)) {
                    return $service->stripeConflictWarningMessage();
                }

                return 'This will mark an invoice paid and reactivate/extend the subscription using the same status machine as Stripe webhooks.';
            })
            ->action(function (?Model $record, array $data, ManualPaymentService $manualPaymentService): void {
                $subscription = self::resolveSubscription($record);

                if ($subscription === null) {
                    Notification::make()
                        ->title('No subscription found')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = $manualPaymentService->record(
                        $subscription,
                        $data,
                        auth()->user(),
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Could not record payment')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();

                    return;
                }

                $body = 'Subscription active until '.$result['period_end']->toDateString().'.';

                if ($result['stripe_warning']) {
                    $body .= ' Note: an active Stripe subscription ID is still on file.';
                }

                Notification::make()
                    ->title('Manual payment recorded')
                    ->body($body)
                    ->success()
                    ->send();
            });
    }

    protected static function resolveSubscription(?Model $record): ?Subscription
    {
        if ($record instanceof Subscription) {
            return $record;
        }

        if ($record instanceof Church) {
            return $record->subscription;
        }

        return null;
    }
}
