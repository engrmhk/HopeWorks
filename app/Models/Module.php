<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Module extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'dependencies',
    ];

    protected function casts(): array
    {
        return [
            'dependencies' => 'array',
        ];
    }

    public function churchModules(): BelongsTo
    {
        return $this->belongsTo(ChurchModule::class, 'key', 'module_key');
    }
}
