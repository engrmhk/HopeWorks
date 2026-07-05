<?php

namespace App\Models;

use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Church extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'synod_instance',
        'slug',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'synod_instance' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ChurchModule::class);
    }

    public function branding(): HasOne
    {
        return $this->hasOne(ChurchBranding::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    protected static function newFactory(): ChurchFactory
    {
        return ChurchFactory::new();
    }
}
