<?php

namespace App\Services;

use App\Models\Church;
use App\Models\LicenseKey;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ChurchInstanceAccessService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LicenseKeyService $licenseKeyService,
    ) {}

    public function hasApiKey(Church $church): bool
    {
        return $church->instance_api_key_hash !== null;
    }

    public function generateApiKey(Church $church, ?Authenticatable $actor = null): string
    {
        $plainKey = Church::generateApiKey();
        $church->setInstanceApiKey($plainKey);

        $this->auditLogService->log(
            actor: $actor ?? auth()->user(),
            action: 'church.api_key_generated',
            subject: $church,
            before: null,
            after: ['has_api_key' => true],
            ip: request()?->ip(),
        );

        return $plainKey;
    }

    public function rotateApiKey(Church $church, ?Authenticatable $actor = null): string
    {
        $hadApiKey = $this->hasApiKey($church);

        $this->licenseKeyService->revokeAllForChurch($church);

        $plainKey = $this->generateApiKey($church, $actor);

        $this->auditLogService->log(
            actor: $actor ?? auth()->user(),
            action: 'church.api_key_rotated',
            subject: $church,
            before: ['had_api_key' => $hadApiKey],
            after: ['has_api_key' => true],
            ip: request()?->ip(),
        );

        return $plainKey;
    }

    public function revokeApiKey(Church $church, ?Authenticatable $actor = null): void
    {
        $hadApiKey = $this->hasApiKey($church);

        $this->licenseKeyService->revokeAllForChurch($church);

        $church->update(['instance_api_key_hash' => null]);

        $this->auditLogService->log(
            actor: $actor ?? auth()->user(),
            action: 'church.api_key_revoked',
            subject: $church,
            before: ['had_api_key' => $hadApiKey],
            after: ['has_api_key' => false],
            ip: request()?->ip(),
        );
    }

    public function revokeLicenseKey(LicenseKey $licenseKey, ?Authenticatable $actor = null): void
    {
        if ($licenseKey->revoked_at !== null) {
            return;
        }

        $this->licenseKeyService->revoke($licenseKey);

        $this->auditLogService->log(
            actor: $actor ?? auth()->user(),
            action: 'license_key.revoked',
            subject: $licenseKey,
            before: ['revoked_at' => null],
            after: ['revoked_at' => $licenseKey->fresh()->revoked_at?->toIso8601String()],
            ip: request()?->ip(),
        );
    }
}
