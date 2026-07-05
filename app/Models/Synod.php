<?php

namespace App\Models;

use App\Enums\SynodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Synod extends Model
{
    protected $fillable = [
        'name',
        'region',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'status' => SynodStatus::class,
        ];
    }

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(LicenseKey::class);
    }

    public function affiliationHistory(): HasMany
    {
        return $this->hasMany(ChurchAffiliationHistory::class);
    }
}
