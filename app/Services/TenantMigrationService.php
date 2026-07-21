<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates cross-instance tenant moves.
 *
 * Companion contract (HopeWorks-church /api/internal/*):
 * - POST tenant-export { church_id } → bundle with entities, row_counts, id_map (when available), schema_version
 * - POST tenant-import { bundle, commit: false } → dry-run reconciliation (transaction rolled back on Core)
 * - POST tenant-import { bundle, commit: true } → live import with FK remapping + integrity report
 * - POST tenant-archive { church_id, correlation_id, row_counts }
 *
 * Dry-run uses commit:false (not a separate scratch DB URL). Core App rolls back the import
 * transaction after reconciliation — destination production data is not persisted.
 */
class TenantMigrationService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected AffiliationService $affiliationService,
    ) {}

    /**
     * Export from source, dry-run import on destination, audit, and cache a one-time token.
     *
     * @return array{
     *     correlation_id: string,
     *     bundle_hash: string,
     *     dry_run_token: string,
     *     reconciliation: array<string, mixed>,
     *     row_counts: array<string, int>,
     *     success: bool
     * }
     */
    public function dryRunMigration(
        Church $church,
        Church $sourceInstance,
        Church $destinationInstance,
        Authenticatable|User|null $actor = null,
    ): array {
        $correlationId = (string) Str::uuid();
        $bundle = $this->exportBundle($sourceInstance, $church);
        $bundleHash = $this->bundleHash($bundle);

        $reconciliation = $this->callImport($destinationInstance, $bundle, commit: false);

        if (($reconciliation['committed'] ?? null) === true) {
            throw ValidationException::withMessages([
                'import' => 'Dry-run unexpectedly committed data on the destination instance.',
            ]);
        }

        $success = $this->reconciliationSucceeded($reconciliation);

        if (! $success) {
            $this->auditLogService->log(
                actor: $actor,
                action: 'tenant.migration_dry_run_failed',
                subject: $church,
                before: [
                    'source_instance_id' => $sourceInstance->id,
                    'destination_instance_id' => $destinationInstance->id,
                ],
                after: [
                    'bundle_hash' => $bundleHash,
                    'reconciliation' => $reconciliation,
                ],
                ip: request()?->ip(),
                highVisibility: true,
                correlationId: $correlationId,
            );

            throw ValidationException::withMessages([
                'import' => 'Dry-run reconciliation failed: '.$this->summarizeReconciliation($reconciliation),
            ]);
        }

        $token = (string) Str::uuid();
        $ttl = (int) config('hopeworks.tenant_migration.dry_run_cache_ttl_seconds', 3600);

        Cache::put($this->dryRunCacheKey($church, $destinationInstance, $bundleHash), [
            'token' => $token,
            'bundle' => $bundle,
            'reconciliation' => $reconciliation,
            'correlation_id' => $correlationId,
            'source_instance_id' => $sourceInstance->id,
            'destination_instance_id' => $destinationInstance->id,
        ], $ttl);

        $this->auditLogService->log(
            actor: $actor,
            action: 'tenant.migration_dry_run_succeeded',
            subject: $church,
            before: [
                'source_instance_id' => $sourceInstance->id,
                'destination_instance_id' => $destinationInstance->id,
            ],
            after: [
                'bundle_hash' => $bundleHash,
                'row_counts' => $reconciliation['row_counts'] ?? ($bundle['row_counts'] ?? []),
                'reconciliation' => $reconciliation,
            ],
            ip: request()?->ip(),
            highVisibility: true,
            correlationId: $correlationId,
        );

        return [
            'correlation_id' => $correlationId,
            'bundle_hash' => $bundleHash,
            'dry_run_token' => $token,
            'reconciliation' => $reconciliation,
            'row_counts' => $reconciliation['row_counts'] ?? ($bundle['row_counts'] ?? []),
            'success' => true,
        ];
    }

    /**
     * Commit a live migration only after a matching successful dry-run token.
     *
     * @return array{correlation_id: string, row_counts: array<string, int>, committed: bool, reconciliation: array<string, mixed>}
     */
    public function migrateChurch(
        Church $church,
        Church $sourceInstance,
        Church $destinationInstance,
        string $reason,
        Authenticatable|User|null $actor = null,
        ?string $dryRunToken = null,
        ?string $bundleHash = null,
    ): array {
        if ($dryRunToken === null || $bundleHash === null) {
            throw ValidationException::withMessages([
                'dry_run' => 'A successful dry run for this export bundle is required before a live migration.',
            ]);
        }

        $cacheKey = $this->dryRunCacheKey($church, $destinationInstance, $bundleHash);
        $cached = Cache::get($cacheKey);

        if (! is_array($cached) || ($cached['token'] ?? null) !== $dryRunToken) {
            throw ValidationException::withMessages([
                'dry_run' => 'Dry-run token is missing or expired. Run dry run again for this export bundle.',
            ]);
        }

        $bundle = $cached['bundle'];
        $correlationId = (string) ($cached['correlation_id'] ?? Str::uuid());
        $secret = $this->serviceSecret();

        $commit = $this->callImport($destinationInstance, $bundle, commit: true);

        if (! ($commit['committed'] ?? false) || ! $this->reconciliationSucceeded($commit)) {
            throw ValidationException::withMessages([
                'import' => 'Destination import commit failed: '.$this->summarizeReconciliation($commit),
            ]);
        }

        $archive = Http::withToken($secret)
            ->timeout(60)
            ->post(rtrim((string) $sourceInstance->instance_url, '/').'/api/internal/tenant-archive', [
                'church_id' => $church->id,
                'correlation_id' => $correlationId,
                'row_counts' => $bundle['row_counts'] ?? [],
            ]);

        $before = [
            'instance_url' => $church->instance_url,
            'synod_id' => $church->synod_id,
        ];

        $church->update([
            'instance_url' => $destinationInstance->instance_url,
        ]);

        $this->affiliationService->changeAffiliation(
            church: $church,
            newSynodId: $destinationInstance->synod_id,
            reason: $reason,
            actor: $actor,
        );

        Cache::forget($cacheKey);

        $this->auditLogService->log(
            actor: $actor,
            action: 'tenant.migration_completed',
            subject: $church,
            before: $before,
            after: [
                'instance_url' => $church->instance_url,
                'bundle_hash' => $bundleHash,
                'row_counts' => $commit['row_counts'] ?? ($bundle['row_counts'] ?? []),
                'reconciliation' => $commit,
                'archive_status' => $archive->json(),
            ],
            ip: request()?->ip(),
            highVisibility: true,
            correlationId: $correlationId,
        );

        return [
            'correlation_id' => $correlationId,
            'row_counts' => $commit['row_counts'] ?? ($bundle['row_counts'] ?? []),
            'committed' => true,
            'reconciliation' => $commit,
        ];
    }

    public function bundleHash(array $bundle): string
    {
        return hash('sha256', json_encode($bundle, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    protected function exportBundle(Church $sourceInstance, Church $church): array
    {
        $response = Http::withToken($this->serviceSecret())
            ->timeout(120)
            ->post(rtrim((string) $sourceInstance->instance_url, '/').'/api/internal/tenant-export', [
                'church_id' => $church->id,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'export' => 'Source tenant export failed: '.$response->body(),
            ]);
        }

        /** @var array<string, mixed> $bundle */
        $bundle = $response->json();

        return $bundle;
    }

    /**
     * @return array<string, mixed>
     */
    protected function callImport(Church $destinationInstance, array $bundle, bool $commit): array
    {
        $response = Http::withToken($this->serviceSecret())
            ->timeout(120)
            ->post(rtrim((string) $destinationInstance->instance_url, '/').'/api/internal/tenant-import', [
                'bundle' => $bundle,
                'commit' => $commit,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'import' => ($commit ? 'Commit' : 'Dry-run').' import failed: '.$response->body(),
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    protected function reconciliationSucceeded(array $reconciliation): bool
    {
        if (isset($reconciliation['success']) && $reconciliation['success'] === false) {
            return false;
        }

        $integrity = $reconciliation['referential_integrity']
            ?? $reconciliation['integrity']
            ?? null;

        if (is_array($integrity) && array_key_exists('ok', $integrity) && $integrity['ok'] === false) {
            return false;
        }

        if (is_array($integrity) && array_key_exists('passed', $integrity) && $integrity['passed'] === false) {
            return false;
        }

        $rowCounts = $reconciliation['row_counts'] ?? null;
        $expected = $reconciliation['expected_counts'] ?? null;

        if (is_array($rowCounts) && is_array($expected)) {
            foreach ($expected as $key => $count) {
                if ((int) ($rowCounts[$key] ?? -1) !== (int) $count) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function summarizeReconciliation(array $reconciliation): string
    {
        if (isset($reconciliation['message']) && is_string($reconciliation['message'])) {
            return $reconciliation['message'];
        }

        return json_encode($reconciliation, JSON_THROW_ON_ERROR) ?: 'unknown error';
    }

    protected function dryRunCacheKey(Church $church, Church $destination, string $bundleHash): string
    {
        return "tenant_migration.dry_run.{$church->id}.{$destination->id}.{$bundleHash}";
    }

    protected function serviceSecret(): string
    {
        return (string) config('hopeworks.internal_service_secret', config('app.key'));
    }
}
