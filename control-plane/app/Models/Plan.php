<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'billing_interval',
        'storage_limit_gb',
        'user_limit',
        'module_eligibility',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'module_eligibility' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
