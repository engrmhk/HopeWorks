<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ChurchAffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class AffiliationService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function changeAffiliation(
        Church $church,
        ?int $newSynodId,
        string $reason,
        Authenticatable|User|null $actor = null,
    ): Church {
        return DB::transaction(function () use ($church, $newSynodId, $reason, $actor) {
            $before = [
                'synod_id' => $church->synod_id,
            ];

            ChurchAffiliationHistory::query()
                ->where('church_id', $church->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()->toDateString()]);

            ChurchAffiliationHistory::create([
                'church_id' => $church->id,
                'synod_id' => $newSynodId,
                'start_date' => now()->toDateString(),
                'reason' => $reason,
                'changed_by_user_id' => $actor?->getAuthIdentifier(),
            ]);

            $church->update(['synod_id' => $newSynodId]);

            $this->auditLogService->log(
                actor: $actor,
                action: 'church.affiliation_changed',
                subject: $church,
                before: $before,
                after: ['synod_id' => $newSynodId, 'reason' => $reason],
                ip: request()?->ip(),
            );

            return $church->fresh(['synod']);
        });
    }
}
