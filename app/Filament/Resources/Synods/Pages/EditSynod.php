<?php

namespace App\Filament\Resources\Synods\Pages;

use App\Filament\Concerns\ManagesLicenseJwtSecret;
use App\Filament\Resources\Synods\SynodResource;
use App\Models\Synod;
use App\Services\LicenseKeyService;
use App\Services\SynodInstanceAccessService;
use App\Support\LicenseJwtSecret;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSynod extends EditRecord
{
    use ManagesLicenseJwtSecret;

    protected static string $resource = SynodResource::class;

    public ?string $revealedApiKey = null;

    /**
     * @var array{status: mixed, enforcement_policy: mixed, platform_notice: mixed, notice_severity: mixed}|null
     */
    protected ?array $enforcementSnapshot = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->revealedApiKey = $this->plainInstanceApiKey();
        $this->jwtSecretInput = LicenseJwtSecret::configured();
        $this->enforcementSnapshot = $this->enforcementState($this->getRecord());
    }

    public function generateSynodApiKey(): void
    {
        $plainKey = app(SynodInstanceAccessService::class)->generateApiKey($this->synodRecord());
        $this->storeRevealedApiKey($plainKey);

        Notification::make()
            ->title('Synod API key generated')
            ->body('Hidden below. Use the eye icon, then paste it into the church app System page for the synod host.')
            ->success()
            ->send();
    }

    public function rotateSynodApiKey(): void
    {
        $plainKey = app(SynodInstanceAccessService::class)->rotateApiKey($this->synodRecord());
        $this->storeRevealedApiKey($plainKey);

        Notification::make()
            ->title('Synod API key rotated')
            ->body('The old key no longer works. Use the eye icon to view the new key.')
            ->warning()
            ->send();
    }

    public function revokeSynodApiKey(): void
    {
        app(SynodInstanceAccessService::class)->revokeApiKey($this->synodRecord());
        $this->revealedApiKey = null;
        session()->forget('hopeworks.revealed_synod_api_key.'.$this->getRecord()->getKey());
        $this->refreshFormData(['instance_api_key_hash', 'instance_api_key']);

        Notification::make()
            ->title('Synod API key revoked')
            ->danger()
            ->send();
    }

    protected function afterSave(): void
    {
        $synod = $this->getRecord()->fresh();

        if ($this->enforcementSnapshot !== $this->enforcementState($synod)) {
            $count = app(LicenseKeyService::class)->revokeAllForSynod($synod);

            Notification::make()
                ->title('Synod access updated')
                ->body("Revoked {$count} cached license(s). Every church under this synod picks up disable/banner on the next Sync Now.")
                ->warning()
                ->send();
        }

        $this->enforcementSnapshot = $this->enforcementState($synod);
    }

    protected function storeRevealedApiKey(string $plainKey): void
    {
        $this->revealedApiKey = $plainKey;
        session()->put('hopeworks.revealed_synod_api_key.'.$this->getRecord()->getKey(), $plainKey);
        $this->getRecord()->refresh();
        $this->refreshFormData(['instance_api_key_hash', 'instance_api_key']);
    }

    protected function plainInstanceApiKey(): ?string
    {
        $synod = $this->getRecord();
        $fromDb = $synod->instance_api_key;

        if (is_string($fromDb) && $fromDb !== '') {
            return $fromDb;
        }

        $stored = session('hopeworks.revealed_synod_api_key.'.$synod->getKey());

        if (is_string($stored) && $stored !== '') {
            $synod->forceFill(['instance_api_key' => $stored])->save();

            return $stored;
        }

        return null;
    }

    /**
     * @return array{status: mixed, enforcement_policy: mixed, platform_notice: mixed, notice_severity: mixed}
     */
    protected function enforcementState(Synod $synod): array
    {
        return [
            'status' => $synod->status?->value,
            'enforcement_policy' => $synod->enforcement_policy?->value,
            'platform_notice' => $synod->platform_notice,
            'notice_severity' => $synod->notice_severity?->value,
        ];
    }

    protected function synodRecord(): Synod
    {
        /** @var Synod $synod */
        $synod = $this->getRecord();

        return $synod;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
