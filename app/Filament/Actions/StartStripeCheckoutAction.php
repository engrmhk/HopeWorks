<?php

namespace App\Filament\Actions;

use App\Models\Subscription;
use App\Services\StripeBillingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class StartStripeCheckoutAction
{
    public static function make(): Action
    {
        return Action::make('startStripeCheckout')
            ->label('Open Stripe Checkout')
            ->icon('heroicon-o-credit-card')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Creates a Stripe Checkout session for this church subscription (test or live mode depending on STRIPE_SECRET).')
            ->action(function (Subscription $record, StripeBillingService $stripeBillingService): void {
                try {
                    $result = $stripeBillingService->createCheckoutSession($record);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Checkout unavailable')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Stripe Checkout session created')
                    ->body("Open this URL to complete payment:\n{$result['url']}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
