<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesLicenseJwtSecret;
use App\Support\LicenseJwtSecret;
use Filament\Pages\Page;

class LicenseConnectionSettings extends Page
{
    use ManagesLicenseJwtSecret;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'License connection';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'License connection';

    protected string $view = 'filament.pages.license-connection-settings';

    public function mount(): void
    {
        $this->jwtSecretInput = LicenseJwtSecret::configured();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
