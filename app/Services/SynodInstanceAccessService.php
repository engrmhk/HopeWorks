<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Synod;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SynodInstanceAccessService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LicenseKeyService $licenseKeyService,
    ) {}

    public function generateApiKey(Synod $synod, ?Authenticatable $actor = null): string
    {
        $plainKey = Church::generateApiKey();
        $synod->setInstanceApiKey($plainKey);

        $this->auditLogService->log(
            actor: $actor ?? auth()->user(),
            action: 'synod.api_key_generated',
            subject: $synod,
            before: null,
            after: ['has_api_key' => true],
            ip: request()?->ip(),
        );

        return $plainKey;
    }

    public function rotateApiKey(Synod $synod, ?Authenticatable $actor = null): string
    {
        $this->licenseKeyService->revokeAllForSynod($synod);

        $plainKey = $this->generateApiKey($synod, $actor);

        $this->auditLogService->log(
            actor: $actor ?? auth()->user(),
            action: 'synod.api_key_rotated',
            subject: $synod,
            before: ['had_api_key' => true],
            after: ['has_api_key' => true],
            ip: request()?->ip(),
        );

        return $plainKey;
    }

    public function revokeApiKey(Synod $synod, ?Authenticatable $actor = null): void
    {
        $this->licenseKeyService->revokeAllForSynod($synod);
        $synod->clearInstanceApiKey();

        $this->auditLogService->log(
            actor: $actor ?? auth()->user(),
            action: 'synod.api_key_revoked',
            subject: $synod,
            before: ['had_api_key' => true],
            after: ['has_api_key' => false],
            ip: request()?->ip(),
        );
    }
}
