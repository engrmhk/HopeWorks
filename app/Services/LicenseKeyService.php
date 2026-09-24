<?php

namespace App\Services;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\LicenseKey;
use App\Models\Subscription;
use App\Models\Synod;
use App\Support\LicenseJwtSecret;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use UnexpectedValueException;

class LicenseKeyService
{
    public function decode(string $jwt): object
    {
        return JWT::decode($jwt, new Key(LicenseJwtSecret::signingKey(), 'HS256'));
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

    public function revokeAllForSynod(Synod $synod): int
    {
        $churchIds = $synod->churches()->pluck('id');

        return LicenseKey::query()
            ->where(function ($query) use ($synod, $churchIds): void {
                $query->where('synod_id', $synod->id);

                if ($churchIds->isNotEmpty()) {
                    $query->orWhereIn('church_id', $churchIds);
                }
            })
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function validateSynodApiKey(?string $plainKey): ?Synod
    {
        if ($plainKey === null || $plainKey === '') {
            return null;
        }

        $hash = Church::hashApiKey($plainKey);

        return Synod::query()->where('instance_api_key_hash', $hash)->first();
    }

    public function issueForSubscription(Subscription $subscription, array $overlay = []): LicenseKey
    {
        $subscription->loadMissing(['plan', 'church', 'synod']);

        $church = $subscription->church;
        $licenseExpiresAt = now()->addHours(config('license.jwt_ttl_hours'));

        $payload = $this->basePayload(
            church: $church,
            synodId: $subscription->synod_id ?? $church?->synod_id,
            planId: $subscription->plan_id,
            enabledModules: $subscription->plan?->module_eligibility ?? [],
            status: $subscription->status->value,
            licenseExpiresAt: $licenseExpiresAt,
            currentPeriodEnd: $subscription->current_period_end,
            graceStartedAt: $subscription->grace_started_at,
            gracePeriodDays: $subscription->grace_period_days,
            overlay: $overlay,
            subscriptionPolicy: $subscription->enforcement_policy,
        );

        return $this->storeLicense($payload, $church?->id, $payload['synod_id'] ?? null, $licenseExpiresAt);
    }

    /**
     * Free synod-host church: no paid subscription required.
     *
     * @param  array<string, mixed>  $overlay
     */
    public function issueComplimentaryForChurch(Church $church, array $overlay = []): LicenseKey
    {
        $licenseExpiresAt = now()->addHours(config('license.jwt_ttl_hours'));

        $payload = $this->basePayload(
            church: $church,
            synodId: $church->synod_id,
            planId: null,
            enabledModules: [],
            status: SubscriptionStatus::Active->value,
            licenseExpiresAt: $licenseExpiresAt,
            currentPeriodEnd: null,
            graceStartedAt: null,
            gracePeriodDays: null,
            overlay: $overlay,
            subscriptionPolicy: EnforcementPolicy::BannerOnly,
        );

        return $this->storeLicense($payload, $church->id, $church->synod_id, $licenseExpiresAt);
    }

    /**
     * @param  array<string, mixed>  $overlay
     */
    public function issueForSynod(Synod $synod, array $overlay = []): LicenseKey
    {
        $licenseExpiresAt = now()->addHours(config('license.jwt_ttl_hours'));

        $payload = $this->basePayload(
            church: null,
            synodId: $synod->id,
            planId: null,
            enabledModules: [],
            status: SubscriptionStatus::Active->value,
            licenseExpiresAt: $licenseExpiresAt,
            currentPeriodEnd: null,
            graceStartedAt: null,
            gracePeriodDays: null,
            overlay: $overlay,
            subscriptionPolicy: $synod->enforcement_policy,
        );

        return $this->storeLicense($payload, null, $synod->id, $licenseExpiresAt);
    }

    /**
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    protected function basePayload(
        ?Church $church,
        ?int $synodId,
        ?int $planId,
        array $enabledModules,
        string $status,
        Carbon $licenseExpiresAt,
        mixed $currentPeriodEnd,
        mixed $graceStartedAt,
        ?int $gracePeriodDays,
        array $overlay,
        ?EnforcementPolicy $subscriptionPolicy,
    ): array {
        $policy = $overlay['enforcement_policy'] ?? $subscriptionPolicy?->value ?? EnforcementPolicy::BannerOnly->value;

        return [
            'church_id' => $church?->id,
            'synod_id' => $overlay['synod_id'] ?? $synodId,
            'plan_id' => $planId,
            'enabled_modules' => $enabledModules,
            'status' => $status,
            'expires_at' => $licenseExpiresAt->toIso8601String(),
            'license_expires_at' => $licenseExpiresAt->toIso8601String(),
            'current_period_end' => $currentPeriodEnd?->toIso8601String(),
            'grace_started_at' => $graceStartedAt?->toIso8601String(),
            'grace_period_days' => $gracePeriodDays,
            'enforcement_policy' => $policy,
            'synod_status' => $overlay['synod_status'] ?? null,
            'platform_notices' => $overlay['platform_notices'] ?? [],
            'iat' => now()->timestamp,
            'exp' => $licenseExpiresAt->timestamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeLicense(array $payload, ?int $churchId, ?int $synodId, Carbon $licenseExpiresAt): LicenseKey
    {
        $jwt = JWT::encode($payload, LicenseJwtSecret::signingKey(), 'HS256');

        return LicenseKey::create([
            'church_id' => $churchId,
            'synod_id' => $synodId,
            'signed_jwt' => $jwt,
            'issued_at' => now(),
            'expires_at' => $licenseExpiresAt,
        ]);
    }
}
