<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ImpersonationToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

class ImpersonationService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function issueToken(Church $church, int $targetUserId, User $actor): array
    {
        $correlationId = (string) Str::uuid();
        $expiresAt = now()->addMinutes((int) config('hopeworks.impersonation_token_ttl_minutes', 5));
        $jti = (string) Str::uuid();

        $payload = [
            'iss' => 'hopeworks-control-plane',
            'sub' => $targetUserId,
            'church_id' => $church->id,
            'correlation_id' => $correlationId,
            'jti' => $jti,
            'iat' => now()->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        $secret = config('hopeworks.impersonation_jwt_secret', config('hopeworks.jwt_secret'));
        $token = JWT::encode($payload, $secret, 'HS256');

        ImpersonationToken::create([
            'church_id' => $church->id,
            'target_user_id' => $targetUserId,
            'issued_by_user_id' => $actor->id,
            'token_hash' => hash('sha256', $jti),
            'correlation_id' => $correlationId,
            'expires_at' => $expiresAt,
        ]);

        $this->auditLogService->log(
            actor: $actor,
            action: 'impersonation.token_issued',
            subject: $church,
            before: null,
            after: [
                'target_user_id' => $targetUserId,
                'correlation_id' => $correlationId,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
            ip: request()?->ip(),
            highVisibility: true,
            correlationId: $correlationId,
        );

        $consumeUrl = rtrim($church->instance_url ?? '', '/').'/api/impersonate/consume';

        return [
            'token' => $token,
            'correlation_id' => $correlationId,
            'expires_at' => $expiresAt->toIso8601String(),
            'consume_url' => $consumeUrl,
        ];
    }
}
