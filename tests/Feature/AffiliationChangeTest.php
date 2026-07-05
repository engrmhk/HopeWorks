<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ControlPlaneAuditLog;
use App\Models\Synod;
use App\Models\User;
use App\Services\AffiliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliation_change_closes_and_opens_history_and_logs_audit(): void
    {
        $actor = User::factory()->create();
        $oldSynod = Synod::create(['name' => 'Old Synod']);
        $newSynod = Synod::create(['name' => 'New Synod']);

        $church = Church::create([
            'synod_id' => $oldSynod->id,
            'name' => 'First Church',
            'subdomain' => 'first-church',
        ]);

        $openHistory = $church->affiliationHistory()->create([
            'synod_id' => $oldSynod->id,
            'start_date' => now()->subYear()->toDateString(),
            'reason' => 'Initial affiliation',
            'changed_by_user_id' => $actor->id,
        ]);

        $service = app(AffiliationService::class);
        $service->changeAffiliation($church, $newSynod->id, 'Regional realignment', $actor);

        $openHistory->refresh();
        $this->assertNotNull($openHistory->end_date);

        $this->assertDatabaseHas('church_affiliation_history', [
            'church_id' => $church->id,
            'synod_id' => $newSynod->id,
            'reason' => 'Regional realignment',
            'end_date' => null,
        ]);

        $church->refresh();
        $this->assertSame($newSynod->id, $church->synod_id);

        $this->assertDatabaseHas('control_plane_audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'church.affiliation_changed',
            'subject_type' => $church->getMorphClass(),
            'subject_id' => $church->id,
        ]);

        $auditLog = ControlPlaneAuditLog::where('action', 'church.affiliation_changed')->first();
        $this->assertSame($oldSynod->id, $auditLog->before['synod_id']);
        $this->assertSame($newSynod->id, $auditLog->after['synod_id']);
    }
}
