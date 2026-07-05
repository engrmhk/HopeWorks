<?php

namespace App\Services;

use App\Models\ChurchModule;
use App\Models\LicenseCache;
use App\Models\Module;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    public function __construct(
        protected ModuleRegistry $moduleRegistry,
    ) {}

    public function current(): LicenseCache
    {
        return LicenseCache::current();
    }

    public function getStatus(): string
    {
        return $this->current()->status;
    }

    public function getEnforcementPolicy(): ?string
    {
        return $this->current()->enforcement_policy;
    }

    public function isGracePeriod(): bool
    {
        return $this->getStatus() === 'grace';
    }

    public function isSuspended(): bool
    {
        return $this->getStatus() === 'suspended';
    }

    public function isActive(): bool
    {
        return $this->getStatus() === 'active';
    }

    public function getGraceExpiresAt(): ?\DateTimeInterface
    {
        $license = $this->current();

        return $license->expires_at;
    }

    public function syncFromControlPlane(): bool
    {
        $url = rtrim(config('hopeworks.control_plane_url'), '/').'/api/v1/heartbeat';
        $apiKey = config('hopeworks.control_plane_api_key');

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post($url, [
                'client_id' => config('app.name'),
                'timestamp' => now()->toIso8601String(),
            ]);

        if (! $response->successful()) {
            Log::warning('License sync failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $this->storeLicensePayload($response->json());

        return true;
    }

    public function storeLicensePayload(array $payload): LicenseCache
    {
        $license = LicenseCache::current();

        $license->fill([
            'status' => $payload['status'] ?? 'active',
            'enforcement_policy' => $payload['enforcement_policy'] ?? null,
            'signed_jwt' => $payload['signed_jwt'] ?? null,
            'expires_at' => isset($payload['expires_at']) ? $payload['expires_at'] : null,
            'synced_at' => now(),
            'metadata' => $payload['metadata'] ?? [],
        ]);

        $license->save();

        if (isset($payload['modules']) && is_array($payload['modules'])) {
            $this->updateChurchModules($payload['modules']);
        }

        return $license;
    }

    public function updateChurchModules(array $modules): void
    {
        foreach ($modules as $moduleData) {
            $key = $moduleData['key'] ?? null;

            if (! $key) {
                continue;
            }

            Module::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $moduleData['name'] ?? $key,
                    'description' => $moduleData['description'] ?? null,
                    'dependencies' => $moduleData['dependencies'] ?? [],
                ]
            );

            $churchId = $moduleData['church_id'] ?? null;

            if ($churchId) {
                ChurchModule::query()->updateOrCreate(
                    ['church_id' => $churchId, 'module_key' => $key],
                    [
                        'enabled' => (bool) ($moduleData['enabled'] ?? false),
                        'settings' => $moduleData['settings'] ?? [],
                    ]
                );
            }
        }
    }

    public function verifyJwt(?string $jwt = null): ?object
    {
        $jwt = $jwt ?? $this->current()->signed_jwt;

        if (! $jwt) {
            return null;
        }

        $secret = config('hopeworks.jwt_secret', config('app.key'));

        try {
            return JWT::decode($jwt, new Key($secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }
    }
}
