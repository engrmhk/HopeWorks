<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
