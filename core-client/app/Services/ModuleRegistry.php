<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ChurchModule;
use App\Models\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class ModuleRegistry
{
    /** @var array<string, array>|null */
    protected ?array $manifests = null;

    public function getManifests(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        $this->manifests = [];

        $modulesPath = app_path('Modules');

        if (! File::isDirectory($modulesPath)) {
            return $this->manifests;
        }

        foreach (File::directories($modulesPath) as $directory) {
            $manifestPath = $directory.'/module.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true);

            if (is_array($manifest) && isset($manifest['key'])) {
                $this->manifests[$manifest['key']] = $manifest;
            }
        }

        return $this->manifests;
    }

    public function getManifest(string $moduleKey): ?array
    {
        return $this->getManifests()[$moduleKey] ?? null;
    }

    public function getChurchId(): ?int
    {
        $user = Auth::user();

        return $user?->church_id;
    }

    public function isEnabled(string $moduleKey): bool
    {
        $churchId = $this->getChurchId();

        if (! $churchId) {
            return false;
        }

        return ChurchModule::query()
            ->where('church_id', $churchId)
            ->where('module_key', $moduleKey)
            ->where('enabled', true)
            ->exists();
    }

    public function getEnabledModules(): array
    {
        $churchId = $this->getChurchId();

        if (! $churchId) {
            return [];
        }

        return ChurchModule::query()
            ->where('church_id', $churchId)
            ->where('enabled', true)
            ->pluck('module_key')
            ->all();
    }

    public function enableModule(string $moduleKey, ?int $churchId = null): void
    {
        $churchId = $churchId ?? $this->getChurchId();

        if (! $churchId) {
            throw new \RuntimeException('Cannot enable module without a church context.');
        }

        $manifest = $this->getManifest($moduleKey);

        if (! $manifest) {
            throw new \InvalidArgumentException("Module [{$moduleKey}] not found.");
        }

        $this->ensureModuleRecord($moduleKey, $manifest);

        ChurchModule::query()->updateOrCreate(
            ['church_id' => $churchId, 'module_key' => $moduleKey],
            ['enabled' => true]
        );

        $this->generatePermissions($moduleKey, $manifest);
    }

    public function disableModule(string $moduleKey, ?int $churchId = null): void
    {
        $churchId = $churchId ?? $this->getChurchId();

        if (! $churchId) {
            throw new \RuntimeException('Cannot disable module without a church context.');
        }

        ChurchModule::query()
            ->where('church_id', $churchId)
            ->where('module_key', $moduleKey)
            ->update(['enabled' => false]);
    }

    public function syncManifestsToDatabase(): void
    {
        foreach ($this->getManifests() as $manifest) {
            $this->ensureModuleRecord($manifest['key'], $manifest);
        }
    }

    protected function ensureModuleRecord(string $moduleKey, array $manifest): void
    {
        Module::query()->updateOrCreate(
            ['key' => $moduleKey],
            [
                'name' => $manifest['name'] ?? $moduleKey,
                'description' => $manifest['description'] ?? null,
                'dependencies' => $manifest['dependencies'] ?? [],
            ]
        );
    }

    protected function generatePermissions(string $moduleKey, array $manifest): void
    {
        $permissions = $manifest['permissions'] ?? [
            "{$moduleKey}.view",
            "{$moduleKey}.manage",
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function getPermissionsForModule(string $moduleKey): Collection
    {
        $manifest = $this->getManifest($moduleKey);

        if (! $manifest) {
            return collect();
        }

        $permissionNames = $manifest['permissions'] ?? [
            "{$moduleKey}.view",
            "{$moduleKey}.manage",
        ];

        return Permission::query()->whereIn('name', $permissionNames)->get();
    }

    public function seedChurchModules(Church $church, array $enabledKeys = []): void
    {
        $this->syncManifestsToDatabase();

        foreach ($this->getManifests() as $key => $manifest) {
            ChurchModule::query()->firstOrCreate(
                ['church_id' => $church->id, 'module_key' => $key],
                ['enabled' => in_array($key, $enabledKeys, true), 'settings' => []]
            );
        }
    }
}
