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
        $expiresAt = now()->addHours(config('license.jwt_ttl_hours'));

        $payload = [
            'church_id' => $church?->id,
            'synod_id' => $subscription->synod_id ?? $church?->synod_id,
            'plan_id' => $subscription->plan_id,
            'enabled_modules' => $subscription->plan?->module_eligibility ?? [],
            'status' => $subscription->status->value,
            'expires_at' => $expiresAt->toIso8601String(),
            'enforcement_policy' => $subscription->enforcement_policy->value,
            'iat' => now()->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        $jwt = JWT::encode($payload, config('license.jwt_secret'), 'HS256');

        return LicenseKey::create([
            'church_id' => $church?->id,
            'synod_id' => $subscription->synod_id ?? $church?->synod_id,
            'signed_jwt' => $jwt,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
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
}
