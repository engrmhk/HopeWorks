<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientHealthSnapshot extends Model
{
    protected $fillable = [
        'church_id',
        'storage_bytes',
        'active_user_count',
        'last_login_at',
        'enabled_modules',
        'app_version',
        'app_tag',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'enabled_modules' => 'array',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
