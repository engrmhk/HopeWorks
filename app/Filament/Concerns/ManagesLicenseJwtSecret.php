<?php

namespace App\Filament\Concerns;

use App\Exceptions\LicenseJwtSecretTooShortException;
use App\Support\LicenseJwtSecret;
use Filament\Notifications\Notification;

trait ManagesLicenseJwtSecret
{
    public string $jwtSecretInput = '';

    public function generateLicenseJwtSecret(): void
    {
        $this->jwtSecretInput = LicenseJwtSecret::generate();

        Notification::make()
            ->title('JWT secret generated and saved')
            ->body('Copy it into each church Settings → System → License JWT secret, then click Sync Now.')
            ->success()
            ->persistent()
            ->send();
    }

    public function saveLicenseJwtSecret(): void
    {
        try {
            LicenseJwtSecret::save($this->jwtSecretInput);
        } catch (LicenseJwtSecretTooShortException $e) {
            Notification::make()
                ->title('Secret too short')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->jwtSecretInput = LicenseJwtSecret::configured();

        Notification::make()
            ->title('JWT secret saved')
            ->body('Use this same value on the church System page.')
            ->success()
            ->send();
    }
}
