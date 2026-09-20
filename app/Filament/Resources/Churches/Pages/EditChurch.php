<?php

namespace App\Filament\Resources\Churches\Pages;

use App\Filament\Actions\ChangeAffiliationAction;
use App\Filament\Actions\ExportOffboardingAction;
use App\Filament\Actions\ForceStatusCheckAction;
use App\Filament\Actions\ImpersonateUserAction;
use App\Filament\Actions\InvalidateCachedLicenseAction;
use App\Filament\Actions\RecordManualPaymentAction;
use App\Filament\Concerns\ManagesLicenseJwtSecret;
use App\Filament\Resources\Churches\ChurchResource;
use App\Models\Church;
use App\Services\ChurchInstanceAccessService;
use App\Support\LicenseJwtSecret;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditChurch extends EditRecord
{
    use ManagesLicenseJwtSecret;
    protected static string $resource = ChurchResource::class;

    public ?string $revealedApiKey = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->revealedApiKey = $this->plainInstanceApiKey();
        $this->jwtSecretInput = LicenseJwtSecret::configured();
    }

    public function revealInstanceConnection(string $plainKey): void
    {
        $this->storeRevealedApiKey($plainKey);
    }

    public function generateChurchApiKey(): void
    {
        $plainKey = app(ChurchInstanceAccessService::class)->generateApiKey($this->churchRecord());
        $this->storeRevealedApiKey($plainKey);

        Notification::make()
            ->title('Instance API key generated')
            ->body('Hidden below. Use the eye icon to view it, then copy it into the church System page.')
            ->success()
            ->send();
    }

    public function rotateChurchApiKey(): void
    {
        $plainKey = app(ChurchInstanceAccessService::class)->rotateApiKey($this->churchRecord());
        $this->storeRevealedApiKey($plainKey);

        Notification::make()
            ->title('Instance API key rotated')
            ->body('The old key no longer works. Use the eye icon to view the new key.')
            ->warning()
            ->send();
    }

    public function revokeChurchApiKey(): void
    {
        app(ChurchInstanceAccessService::class)->revokeApiKey($this->churchRecord());
        $this->revealedApiKey = null;
        session()->forget('hopeworks.revealed_api_key.'.$this->getRecord()->getKey());
        $this->refreshFormData(['instance_api_key_hash', 'instance_api_key']);

        Notification::make()
            ->title('Instance API key revoked')
            ->danger()
            ->send();
    }

    protected function storeRevealedApiKey(string $plainKey): void
    {
        $this->revealedApiKey = $plainKey;
        session()->put('hopeworks.revealed_api_key.'.$this->getRecord()->getKey(), $plainKey);
        $this->getRecord()->refresh();
        $this->refreshFormData(['instance_api_key_hash', 'instance_api_key']);
    }

    protected function plainInstanceApiKey(): ?string
    {
        $church = $this->getRecord();
        $fromDb = $church->instance_api_key;

        if (is_string($fromDb) && $fromDb !== '') {
            return $fromDb;
        }

        $stored = session('hopeworks.revealed_api_key.'.$church->getKey());

        if (is_string($stored) && $stored !== '' && $church->verifyInstanceApiKey($stored)) {
            $church->forceFill(['instance_api_key' => $stored])->save();

            return $stored;
        }

        return null;
    }

    protected function churchRecord(): Church
    {
        /** @var Church $church */
        $church = $this->getRecord();

        return $church;
    }

    protected function getHeaderActions(): array
    {
        return [
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
        ];
    }
}
