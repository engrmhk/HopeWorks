<?php

namespace App\Models;

use App\Enums\ChurchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Church extends Model
{
    protected $fillable = [
        'synod_id',
        'name',
        'status',
        'subdomain',
        'custom_domain',
        'instance_url',
        'instance_api_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChurchStatus::class,
        ];
    }

    public function synod(): BelongsTo
    {
        return $this->belongsTo(Synod::class);
    }

    public function affiliationHistory(): HasMany
    {
        return $this->hasMany(ChurchAffiliationHistory::class);
    }

    public function currentAffiliation(): HasOne
    {
        return $this->hasOne(ChurchAffiliationHistory::class)
            ->whereNull('end_date')
            ->latestOfMany('start_date');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(LicenseKey::class);
    }

    public function setInstanceApiKey(string $plainKey): void
    {
        $this->instance_api_key_hash = self::hashApiKey($plainKey);
        $this->save();
    }

    public function verifyInstanceApiKey(string $plainKey): bool
    {
        if ($this->instance_api_key_hash === null) {
            return false;
        }

        return hash_equals($this->instance_api_key_hash, self::hashApiKey($plainKey));
    }

    public static function hashApiKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public static function generateApiKey(): string
    {
        return 'hw_'.Str::random(40);
    }

    public static function generateSubdomain(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'church';
        }

        $subdomain = $base;
        $counter = 1;

        while (static::where('subdomain', $subdomain)->exists()) {
            $subdomain = $base.'-'.$counter;
            $counter++;
        }

        return $subdomain;
    }
}
