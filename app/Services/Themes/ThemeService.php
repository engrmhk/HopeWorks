<?php

namespace App\Services\Themes;

use App\Models\Theme;
use App\Models\User;
use App\Support\ThemeDefaults;
use Illuminate\Support\Facades\DB;

class ThemeService
{
    public function __construct(
        protected ThemeCompiler $compiler,
        protected ThemeConfigValidator $validator,
    ) {}

    public function ensureActiveTheme(?User $actor = null): Theme
    {
        $theme = Theme::query()->where('is_active', true)->first();

        if ($theme !== null) {
            return $theme;
        }

        return Theme::query()->create([
            'name' => 'Control Plane',
            'is_active' => true,
            'config' => ThemeDefaults::all(),
            'created_by' => $actor?->id,
        ]);
    }

    public function activate(Theme $theme): Theme
    {
        return DB::transaction(function () use ($theme): Theme {
            Theme::query()->where('id', '!=', $theme->id)->update(['is_active' => false]);

            $theme->is_active = true;
            $theme->save();
            $this->compiler->invalidate();

            return $theme->fresh();
        });
    }

    /** @param  array<string, mixed>  $config */
    public function saveTheme(Theme $theme, string $name, array $config, bool $activate = true): Theme
    {
        $theme->name = $name;
        $theme->config = array_replace_recursive(ThemeDefaults::all(), $this->validator->validate($config));
        $theme->save();

        if ($activate) {
            $this->activate($theme);
        } else {
            $this->compiler->invalidate();
        }

        return $theme->fresh();
    }

    public function resetToHardcodedDefaults(Theme $theme): Theme
    {
        $theme->config = ThemeDefaults::all();
        $theme->name = 'Control Plane';
        $theme->save();
        $this->activate($theme);

        return $theme->fresh();
    }
}
