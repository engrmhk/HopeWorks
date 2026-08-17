<?php

namespace App\Models;

use App\Support\ThemeDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'config',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, mixed> */
    public function mergedConfig(): array
    {
        return array_replace_recursive(ThemeDefaults::all(), is_array($this->config) ? $this->config : []);
    }

    public function configValue(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->mergedConfig(), $path, $default);
    }
}
