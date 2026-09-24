<?php

namespace App\Models;

use App\Enums\EnforcementPolicy;
use App\Enums\NoticeSeverity;
use App\Enums\SynodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Synod extends Model
{
    protected $fillable = [
        'name',
        'shared_subdomain',
        'instance_url',
        'instance_api_key_hash',
        'instance_api_key',
        'last_heartbeat_at',
        'region',
        'status',
        'enforcement_policy',
        'platform_notice',
        'notice_severity',
        'contact_name',
        'contact_email',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'status' => SynodStatus::class,
            'enforcement_policy' => EnforcementPolicy::class,
            'notice_severity' => NoticeSeverity::class,
            'instance_api_key' => 'encrypted',
            'last_heartbeat_at' => 'datetime',
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

    public function setInstanceApiKey(string $plainKey): void
    {
        $this->instance_api_key_hash = Church::hashApiKey($plainKey);
        $this->instance_api_key = $plainKey;
        $this->save();
    }

    public function clearInstanceApiKey(): void
    {
        $this->forceFill([
            'instance_api_key_hash' => null,
            'instance_api_key' => null,
        ])->save();
    }

    public function hasApiKey(): bool
    {
        return $this->instance_api_key_hash !== null;
    }
}
