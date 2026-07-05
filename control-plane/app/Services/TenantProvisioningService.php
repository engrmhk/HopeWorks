<?php

namespace App\Services;

use App\Enums\ChurchStatus;
use App\Enums\SynodStatus;
use App\Models\Church;
use App\Models\Synod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array{name: string, synod_id?: int|null, synod_name?: string|null, plan_id?: int|null}  $data
     */
    public function provisionNewClient(array $data): Church
    {
        $synodId = $data['synod_id'] ?? null;

        if ($synodId === null && ! empty($data['synod_name'])) {
            $synod = Synod::create([
                'name' => $data['synod_name'],
                'status' => SynodStatus::Active,
            ]);
            $synodId = $synod->id;
        }

        $subdomain = Church::generateSubdomain($data['name']);
        $apiKey = Church::generateApiKey();

        $church = Church::create([
            'synod_id' => $synodId,
            'name' => $data['name'],
            'status' => ChurchStatus::Provisioning,
            'subdomain' => $subdomain,
            'instance_url' => "https://{$subdomain}.hopeworks.app",
            'instance_api_key_hash' => Church::hashApiKey($apiKey),
        ]);

        $this->callProvisioningWebhook($church, $apiKey);

        $this->auditLogService->log(
            actor: auth()->user(),
            action: 'tenant.provisioned',
            subject: $church,
            before: null,
            after: [
                'church_id' => $church->id,
                'subdomain' => $church->subdomain,
                'synod_id' => $church->synod_id,
            ],
            ip: request()?->ip(),
        );

        return $church->fresh(['synod']);
    }

    protected function callProvisioningWebhook(Church $church, string $apiKey): void
    {
        $webhookUrl = config('services.tenant_provisioning.webhook_url');

        if ($webhookUrl === null) {
            Log::info('Tenant provisioning webhook stub called.', [
                'church_id' => $church->id,
                'subdomain' => $church->subdomain,
                'instance_url' => $church->instance_url,
                'api_key_prefix' => Str::substr($apiKey, 0, 8).'...',
            ]);

            return;
        }

        Http::post($webhookUrl, [
            'church_id' => $church->id,
            'subdomain' => $church->subdomain,
            'instance_url' => $church->instance_url,
            'api_key' => $apiKey,
        ]);
    }
}
