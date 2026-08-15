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
        protected ChurchInstanceAccessService $churchInstanceAccessService,
    ) {}

    /**
     * @param  array{name: string, synod_id?: int|null, synod_name?: string|null, plan_id?: int|null}  $data
     * @return array{church: Church, api_key: string}
     */
    public function provisionNewClient(array $data): array
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

        $church = Church::create([
            'synod_id' => $synodId,
            'name' => $data['name'],
            'status' => ChurchStatus::Provisioning,
            'subdomain' => $subdomain,
            'instance_url' => "https://{$subdomain}.hopeworks.app",
        ]);

        $apiKey = $this->churchInstanceAccessService->generateApiKey($church);

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

        return [
            'church' => $church->fresh(['synod']),
            'api_key' => $apiKey,
        ];
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
