<?php

namespace App\Services;

use App\Enums\DataRetentionStatus;
use App\Enums\TenantExportJobStatus;
use App\Models\Church;
use App\Models\Synod;
use App\Models\TenantExportJob;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class TenantOffboardingService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function exportChurch(Church $church, Authenticatable|User|null $actor = null): TenantExportJob
    {
        return $this->runExport('church', $church->id, [$church], $actor);
    }

    public function exportSynod(Synod $synod, Authenticatable|User|null $actor = null): TenantExportJob
    {
        $churches = $synod->churches()->get();

        return $this->runExport('synod', $synod->id, $churches->all(), $actor);
    }

    /** @param array<int, Church> $churches */
    protected function runExport(string $scope, int $scopeId, array $churches, Authenticatable|User|null $actor): TenantExportJob
    {
        $correlationId = (string) Str::uuid();
        $secret = config('hopeworks.internal_service_secret', config('app.key'));

        $job = TenantExportJob::create([
            'scope' => $scope,
            'scope_id' => $scopeId,
            'requested_by' => $actor?->getAuthIdentifier(),
            'status' => TenantExportJobStatus::Processing->value,
            'correlation_id' => $correlationId,
        ]);

        $packageDir = storage_path('app/offboarding/'.$job->id);
        if (! is_dir($packageDir)) {
            mkdir($packageDir, 0755, true);
        }

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'scope_id' => $scopeId,
            'churches' => [],
        ];

        foreach ($churches as $church) {
            if ($church->instance_url === null) {
                continue;
            }

            $response = Http::withToken($secret)
                ->timeout(120)
                ->post(rtrim($church->instance_url, '/').'/api/internal/tenant-export', [
                    'church_id' => $church->id,
                ]);

            if (! $response->successful()) {
                $job->update(['status' => TenantExportJobStatus::Failed->value]);

                throw new \RuntimeException('Export failed for church '.$church->id);
            }

            $bundle = $response->json();
            $slug = $church->subdomain ?? ('church-'.$church->id);
            $churchDir = $packageDir.'/'.$slug;
            mkdir($churchDir, 0755, true);

            file_put_contents($churchDir.'/data.json', json_encode($bundle, JSON_PRETTY_PRINT));
            $manifest['churches'][] = [
                'church_id' => $church->id,
                'name' => $church->name,
                'directory' => $slug,
                'row_counts' => $bundle['row_counts'] ?? [],
            ];
        }

        file_put_contents($packageDir.'/README.md', $this->buildReadme($manifest));
        file_put_contents($packageDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $zipPath = 'offboarding/package-'.$job->id.'.zip';
        $this->zipDirectory($packageDir, storage_path('app/'.$zipPath));

        $retentionDays = (int) config('hopeworks.offboarding.retention_days', 90);

        foreach ($churches as $church) {
            $church->update([
                'data_retention_status' => DataRetentionStatus::RetainedLocked->value,
                'data_retention_until' => now()->addDays($retentionDays),
            ]);
        }

        $job->update([
            'status' => TenantExportJobStatus::Completed->value,
            'file_path' => $zipPath,
            'metadata' => $manifest,
        ]);

        $this->auditLogService->log(
            actor: $actor,
            action: 'tenant.offboarding_export',
            subject: $job,
            before: null,
            after: [
                'scope' => $scope,
                'scope_id' => $scopeId,
                'churches' => count($manifest['churches']),
                'file_path' => $zipPath,
            ],
            ip: request()?->ip(),
            highVisibility: true,
            correlationId: $correlationId,
        );

        return $job->fresh();
    }

    /** @param array<string, mixed> $manifest */
    protected function buildReadme(array $manifest): string
    {
        $lines = [
            '# Hope Works Tenant Export Package',
            '',
            'This package contains structured exports for offboarding from the Hope Works platform.',
            '',
            '## Package layout',
            '- `manifest.json` — export metadata and per-church row counts',
            '- `<church-subdomain>/data.json` — full tenant data bundle for that church',
            '',
            '## Entity keys',
            'Each `data.json` includes `entities` keyed by domain:',
            '- `people`, `households` — member records',
            '- `ministries`, `service_types`, `service_occurrences`, `attendance_records` — services & attendance',
            '- `mentorship_groups`, `mentorship_sessions` — mentorship',
            '- `discipleship_levels`, `discipleship_sessions`, `discipleship_certificates` — discipleship',
            '- `church_events`, `event_occurrences` — events',
            '- `giving_categories`, `giving_transactions`, `exchange_rates` — giving (record-keeping only)',
            '- `communication_templates`, `communication_campaigns`, `automation_rules` — communications metadata',
            '- `report_definitions`, `dashboard_layouts`, `saved_filters` — reporting configuration',
            '',
            '## Field conventions',
            '- All records are scoped by `church_id`',
            '- Timestamps use ISO-8601 in JSON',
            '- `schema_version` in each bundle must match the exporting platform version',
            '',
            '## Churches in this package',
        ];

        foreach ($manifest['churches'] as $church) {
            $lines[] = '- '.$church['name'].' (`'.$church['directory'].'`)';
        }

        return implode("\n", $lines)."\n";
    }

    protected function zipDirectory(string $source, string $destination): void
    {
        $zip = new ZipArchive;

        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create offboarding zip.');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relative = substr($filePath, strlen($source) + 1);
            $zip->addFile($filePath, $relative);
        }

        $zip->close();
    }
}
