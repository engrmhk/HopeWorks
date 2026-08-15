<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LicenseKey extends Model
{
    protected $fillable = [
        'church_id',
        'synod_id',
        'signed_jwt',
        'issued_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function synod(): BelongsTo
    {
        return $this->belongsTo(Synod::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function displayStatus(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
