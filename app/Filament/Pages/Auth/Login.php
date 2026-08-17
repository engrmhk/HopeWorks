<?php

namespace App\Filament\Pages\Auth;

use App\Services\Themes\ThemeResolver;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable|null
    {
        $title = Arr::get($this->loginPageConfig(), 'welcome_title');

        return filled($title) ? (string) $title : parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $subtitle = Arr::get($this->loginPageConfig(), 'welcome_subtitle');

        if (filled($subtitle)) {
            return (string) $subtitle;
        }

        return parent::getSubheading();
    }

    /** @return array<string, mixed> */
    protected function loginPageConfig(): array
    {
        return Arr::get(app(ThemeResolver::class)->resolvedConfig(), 'login_page', []);
    }
}
