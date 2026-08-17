<?php

namespace App\Services\Themes;

use App\Models\Theme;
use App\Support\ThemeDefaults;

class ThemeResolver
{
    public function resolve(): ?Theme
    {
        return Theme::query()->where('is_active', true)->first();
    }

    /** @return array<string, mixed> */
    public function resolvedConfig(): array
    {
        return $this->resolve()?->mergedConfig() ?? ThemeDefaults::all();
    }
}
