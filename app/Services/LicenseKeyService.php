<?php

namespace App\Services;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\LicenseKey;
use App\Models\Subscription;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use UnexpectedValueException;

class LicenseKeyService
{
    public function issueForSubscription(Subscription $subscription): LicenseKey
    {
        $subscription->loadMissing(['plan', 'church', 'synod']);

        $church = $subscription->church;
        $licenseExpiresAt = now()->addHours(config('license.jwt_ttl_hours'));

        $payload = [
            'church_id' => $church?->id,
            'synod_id' => $subscription->synod_id ?? $church?->synod_id,
            'plan_id' => $subscription->plan_id,
            'enabled_modules' => $subscription->plan?->module_eligibility ?? [],
            'status' => $subscription->status->value,
            // JWT / re-sync TTL — NOT the billing period end.
            'expires_at' => $licenseExpiresAt->toIso8601String(),
            'license_expires_at' => $licenseExpiresAt->toIso8601String(),
            // Billing subscription period (what staff edit on the Control Plane).
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'grace_started_at' => $subscription->grace_started_at?->toIso8601String(),
            'grace_period_days' => $subscription->grace_period_days,
            'enforcement_policy' => $subscription->enforcement_policy->value,
            'iat' => now()->timestamp,
            'exp' => $licenseExpiresAt->timestamp,
        ];

        $jwt = JWT::encode($payload, config('license.jwt_secret'), 'HS256');

        return LicenseKey::create([
            'church_id' => $church?->id,
            'synod_id' => $subscription->synod_id ?? $church?->synod_id,
            'signed_jwt' => $jwt,
            'issued_at' => now(),
            'expires_at' => $licenseExpiresAt,
        ]);
    }

    public function decode(string $jwt): object
    {
        return JWT::decode($jwt, new Key(config('license.jwt_secret'), 'HS256'));
    }

    public function validateApiKey(?string $plainKey): ?Church
    {
        if ($plainKey === null || $plainKey === '') {
            return null;
        }

        $hash = Church::hashApiKey($plainKey);

        return Church::where('instance_api_key_hash', $hash)->first();
    }

    public function validateLicensePayload(object $payload): bool
    {
        if (! isset($payload->exp) || Carbon::createFromTimestamp($payload->exp)->isPast()) {
            return false;
        }

        if (! isset($payload->status)) {
            return false;
        }

        try {
            SubscriptionStatus::from($payload->status);
            EnforcementPolicy::from($payload->enforcement_policy ?? EnforcementPolicy::BannerOnly->value);
        } catch (\ValueError) {
            return false;
        }

        return true;
    }

    public function assertValidLicense(string $jwt): object
    {
        try {
            $payload = $this->decode($jwt);
        } catch (UnexpectedValueException|\DomainException $e) {
            throw new UnexpectedValueException('Invalid license key.', 0, $e);
        }

        if (! $this->validateLicensePayload($payload)) {
            throw new UnexpectedValueException('License key is expired or invalid.');
        }

        return $payload;
    }

    public function revoke(LicenseKey $licenseKey): void
    {
        if ($licenseKey->revoked_at !== null) {
            return;
        }

        $licenseKey->update(['revoked_at' => now()]);
    }

    public function revokeAllForChurch(Church $church): int
    {
        return LicenseKey::query()
            ->where('church_id', $church->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
