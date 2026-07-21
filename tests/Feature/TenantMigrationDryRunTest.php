<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ControlPlaneAuditLog;
use App\Models\Synod;
use App\Models\User;
use App\Services\TenantMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantMigrationDryRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hopeworks.internal_service_secret' => 'test-internal-secret',
            'hopeworks.tenant_migration.dry_run_cache_ttl_seconds' => 3600,
            'cache.default' => 'array',
        ]);
    }

    public function test_live_migration_requires_prior_dry_run_token(): void
    {
        [$church, $source, $destination] = $this->churches();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('successful dry run');

        app(TenantMigrationService::class)->migrateChurch(
            church: $church,
            sourceInstance: $source,
            destinationInstance: $destination,
            reason: 'move to synod host',
            actor: User::factory()->create(),
        );
    }

    public function test_successful_dry_run_is_audited_and_unlocks_commit(): void
    {
        [$church, $source, $destination] = $this->churches();
        $actor = User::factory()->create();
        $bundle = $this->sampleBundle();

        Http::fake([
            'https://source.test/api/internal/tenant-export' => Http::response($bundle),
            'https://dest.test/api/internal/tenant-import' => function ($request) {
                $payload = $request->data();
                $commit = (bool) ($payload['commit'] ?? false);

                return Http::response([
                    'committed' => $commit,
                    'row_counts' => $payload['bundle']['row_counts'],
                    'expected_counts' => $payload['bundle']['row_counts'],
                    'referential_integrity' => ['ok' => true, 'sampled' => 3],
                ]);
            },
            'https://source.test/api/internal/tenant-archive' => Http::response(['archived' => true]),
        ]);

        $service = app(TenantMigrationService::class);

        $dryRun = $service->dryRunMigration($church, $source, $destination, $actor);

        $this->assertTrue($dryRun['success']);
        $this->assertNotEmpty($dryRun['dry_run_token']);
        $this->assertDatabaseHas('control_plane_audit_logs', [
            'action' => 'tenant.migration_dry_run_succeeded',
            'subject_id' => $church->id,
        ]);

        $result = $service->migrateChurch(
            church: $church,
            sourceInstance: $source,
            destinationInstance: $destination,
            reason: 'verified move',
            actor: $actor,
            dryRunToken: $dryRun['dry_run_token'],
            bundleHash: $dryRun['bundle_hash'],
        );

        $this->assertTrue($result['committed']);
        $this->assertDatabaseHas('control_plane_audit_logs', [
            'action' => 'tenant.migration_completed',
            'subject_id' => $church->id,
        ]);

        $this->assertNotEquals(
            ControlPlaneAuditLog::query()->where('action', 'tenant.migration_dry_run_succeeded')->value('action'),
            'tenant.migration_completed',
        );

        $church->refresh();
        $this->assertSame('https://dest.test', $church->instance_url);
        $this->assertSame($destination->synod_id, $church->synod_id);
    }

    public function test_failed_dry_run_does_not_call_commit_and_is_audited(): void
    {
        [$church, $source, $destination] = $this->churches();
        $bundle = $this->sampleBundle();

        Http::fake([
            'https://source.test/api/internal/tenant-export' => Http::response($bundle),
            'https://dest.test/api/internal/tenant-import' => Http::response([
                'committed' => false,
                'row_counts' => ['people' => 1],
                'expected_counts' => ['people' => 2],
                'referential_integrity' => ['ok' => false, 'issues' => ['household_id orphan']],
                'success' => false,
                'message' => 'colliding dataset',
            ], 200),
        ]);

        try {
            app(TenantMigrationService::class)->dryRunMigration($church, $source, $destination);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Dry-run reconciliation failed', $e->getMessage());
        }

        $this->assertDatabaseHas('control_plane_audit_logs', [
            'action' => 'tenant.migration_dry_run_failed',
            'subject_id' => $church->id,
        ]);

        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'tenant-import')
            && ($request['commit'] ?? false) === true);
    }

    /**
     * @return array{0: Church, 1: Church, 2: Church}
     */
    protected function churches(): array
    {
        $synod = Synod::create(['name' => 'North Synod']);

        $source = Church::create([
            'name' => 'Source Host',
            'subdomain' => 'source-host',
            'instance_url' => 'https://source.test',
            'synod_id' => null,
        ]);

        $destination = Church::create([
            'name' => 'Dest Host',
            'subdomain' => 'dest-host',
            'instance_url' => 'https://dest.test',
            'synod_id' => $synod->id,
        ]);

        $church = Church::create([
            'name' => 'Migrating Parish',
            'subdomain' => 'migrating-parish',
            'instance_url' => 'https://source.test',
            'synod_id' => null,
        ]);

        return [$church, $source, $destination];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sampleBundle(): array
    {
        return [
            'schema_version' => '1.0.0',
            'row_counts' => [
                'people' => 2,
                'households' => 1,
            ],
            'id_map' => [
                'people' => [10 => 10, 11 => 11],
                'households' => [5 => 5],
            ],
            'entities' => [
                'people' => [
                    ['id' => 10, 'church_id' => 1, 'first_name' => 'A'],
                    ['id' => 11, 'church_id' => 1, 'first_name' => 'B'],
                ],
                'households' => [
                    ['id' => 5, 'church_id' => 1, 'name' => 'Smith'],
                ],
            ],
        ];
    }
}
