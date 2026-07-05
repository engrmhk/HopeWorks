<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseCache extends Model
{
    protected $table = 'license_cache';

    protected $fillable = [
        'status',
        'enforcement_policy',
        'signed_jwt',
        'expires_at',
        'synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'status' => 'active',
            'enforcement_policy' => null,
        ]);
    }
}
