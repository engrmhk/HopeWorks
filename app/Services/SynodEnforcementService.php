<?php

namespace App\Services;

use App\Enums\EnforcementPolicy;
use App\Enums\NoticeSeverity;
use App\Enums\SynodStatus;
use App\Models\Synod;

class SynodEnforcementService
{
    /**
     * @return array{
     *     synod_id: int|null,
     *     synod_status: string|null,
     *     enforcement_policy: string,
     *     platform_notices: list<array{scope: string, severity: string, message: string}>
     * }
     */
    public function overlay(?Synod $synod, ?EnforcementPolicy $subscriptionPolicy = null): array
    {
        $policy = $subscriptionPolicy ?? EnforcementPolicy::BannerOnly;
        $notices = [];

        if ($synod === null) {
            return [
                'synod_id' => null,
                'synod_status' => null,
                'enforcement_policy' => $policy->value,
                'platform_notices' => [],
            ];
        }

        if ($synod->status === SynodStatus::Inactive) {
            $policy = EnforcementPolicy::FullLock;
            $notices[] = [
                'scope' => 'synod',
                'severity' => NoticeSeverity::Critical->value,
                'message' => filled($synod->platform_notice)
                    ? (string) $synod->platform_notice
                    : 'This synod has been disabled by Hope Works. All churches under it are locked until it is reactivated.',
            ];
        } else {
            if ($synod->enforcement_policy instanceof EnforcementPolicy) {
                $policy = $this->stricter($policy, $synod->enforcement_policy);
            }

            if (filled($synod->platform_notice)) {
                $notices[] = [
                    'scope' => 'synod',
                    'severity' => ($synod->notice_severity ?? NoticeSeverity::Warning)->value,
                    'message' => (string) $synod->platform_notice,
                ];
            }
        }

        return [
            'synod_id' => $synod->id,
            'synod_status' => $synod->status->value,
            'enforcement_policy' => $policy->value,
            'platform_notices' => $notices,
        ];
    }

    protected function stricter(?EnforcementPolicy $a, EnforcementPolicy $b): EnforcementPolicy
    {
        $rank = [
            EnforcementPolicy::BannerOnly->value => 1,
            EnforcementPolicy::ReadOnly->value => 2,
            EnforcementPolicy::FullLock->value => 3,
        ];

        if ($a === null) {
            return $b;
        }

        return ($rank[$b->value] ?? 0) > ($rank[$a->value] ?? 0) ? $b : $a;
    }
}
