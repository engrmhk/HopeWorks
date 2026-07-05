<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SynodDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Synod Dashboard';

    protected static ?string $title = 'Synod Dashboard';

    protected static ?string $slug = 'synod-dashboard';

    protected string $view = 'filament.pages.synod-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSynodAdmin() ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSynodAdmin() ?? false;
    }
}
