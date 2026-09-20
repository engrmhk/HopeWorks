<?php

namespace App\Filament\Resources\Churches\Pages;

use App\Filament\Actions\ChangeAffiliationAction;
use App\Filament\Actions\ExportOffboardingAction;
use App\Filament\Actions\ForceStatusCheckAction;
use App\Filament\Actions\GenerateInstanceApiKeyAction;
use App\Filament\Actions\ImpersonateUserAction;
use App\Filament\Actions\InvalidateCachedLicenseAction;
use App\Filament\Actions\RecordManualPaymentAction;
use App\Filament\Actions\RevokeInstanceApiKeyAction;
use App\Filament\Actions\RotateInstanceApiKeyAction;
use App\Filament\Resources\Churches\ChurchResource;
use App\Support\LicenseJwtSecret;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class EditChurch extends EditRecord
{
    protected static string $resource = ChurchResource::class;

    public ?string $revealedApiKey = null;

    public function revealInstanceConnection(string $plainKey): void
    {
        $this->revealedApiKey = $plainKey;
        $this->record->refresh();

        Notification::make()
            ->title('Copy these values into the church app')
            ->body('Settings → System → Control Plane connection. The API key is shown only once.')
            ->success()
            ->send();

        $this->mountAction('copyInstanceConnection');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                GenerateInstanceApiKeyAction::make(),
                RotateInstanceApiKeyAction::make(),
                RevokeInstanceApiKeyAction::make(),
            ])
                ->label('API Key')
                ->icon('heroicon-m-key')
                ->color('warning')
                ->button()
                ->dropdownWidth(Width::ExtraSmall),

            ActionGroup::make([
                RecordManualPaymentAction::make(),
                ForceStatusCheckAction::make(),
                InvalidateCachedLicenseAction::make(),
            ])
                ->label('Billing & License')
                ->icon('heroicon-m-credit-card')
                ->color('success')
                ->button()
                ->dropdownWidth(Width::Small),

            ActionGroup::make([
                ChangeAffiliationAction::make(),
                ImpersonateUserAction::make(),
                ExportOffboardingAction::make(),
            ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->button()
                ->dropdownWidth(Width::Small),

            DeleteAction::make(),

            Action::make('copyInstanceConnection')
                ->label('Church connection values')
                ->icon(Heroicon::ClipboardDocument)
                ->hidden()
                ->modalHeading('Paste into church System → Control Plane connection')
                ->modalDescription('Use the same labels as the church dashboard. The Instance API key cannot be recovered after you close this window.')
                ->modalWidth(Width::Large)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Done')
                ->fillForm(fn (): array => $this->instanceConnectionFormState())
                ->schema([
                    Placeholder::make('jwt_warning')
                        ->visible(fn (): bool => ! LicenseJwtSecret::isReady())
                        ->content('LICENSE_JWT_SECRET on this Control Plane is missing or shorter than 32 characters. Heartbeat will return HTTP 503 until you set the same long secret in Control Plane .env and on the church System page. The Instance API key is not the JWT secret.'),
                    TextInput::make('control_plane_url')
                        ->label('Control Plane URL')
                        ->disabled()
                        ->copyable()
                        ->helperText('Base URL only, e.g. https://control.hopeworksagency.com'),
                    TextInput::make('church_id')
                        ->label('Control Plane church ID')
                        ->disabled()
                        ->copyable()
                        ->helperText('Optional on the church form. Copy the numeric church ID from this record.'),
                    TextInput::make('api_key')
                        ->label('Instance API key')
                        ->password()
                        ->revealable()
                        ->disabled()
                        ->copyable()
                        ->helperText('Paste into Instance API key. Leave the church “Remove API key” box unchecked.'),
                    TextInput::make('jwt_secret')
                        ->label('License JWT secret')
                        ->password()
                        ->revealable()
                        ->disabled()
                        ->copyable()
                        ->helperText(LicenseJwtSecret::operatorMessage()),
                ]),
        ];
    }

    /** @return array<string, string> */
    protected function instanceConnectionFormState(): array
    {
        return [
            'control_plane_url' => rtrim((string) config('app.url'), '/'),
            'church_id' => (string) $this->getRecord()->getKey(),
            'api_key' => (string) $this->revealedApiKey,
            'jwt_secret' => LicenseJwtSecret::configured(),
        ];
    }
}
