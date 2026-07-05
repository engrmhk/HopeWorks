<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class SyncLicense extends Command
{
    protected $signature = 'hopeworks:sync-license';

    protected $description = 'Sync license status from the HopeWorks Control Plane';

    public function handle(LicenseService $licenseService): int
    {
        $this->info('Syncing license from Control Plane...');

        if ($licenseService->syncFromControlPlane()) {
            $license = $licenseService->current();
            $this->info("License synced: status={$license->status}, policy={$license->enforcement_policy}");

            return self::SUCCESS;
        }

        $this->error('License sync failed.');

        return self::FAILURE;
    }
}
