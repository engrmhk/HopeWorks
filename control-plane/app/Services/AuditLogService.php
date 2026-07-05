<?php

namespace App\Services;

use App\Models\ControlPlaneAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AuditLogService
{
    public function log(
        Authenticatable|int|null $actor,
        string $action,
        Model|string|null $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ip = null,
    ): ControlPlaneAuditLog {
        $actorId = match (true) {
            $actor instanceof Authenticatable => $actor->getAuthIdentifier(),
            is_int($actor) => $actor,
            default => null,
        };

        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = $subject->getMorphClass();
            $subjectId = $subject->getKey();
        } elseif (is_string($subject)) {
            $subjectType = $subject;
        }

        return ControlPlaneAuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ip,
        ]);
    }
}
