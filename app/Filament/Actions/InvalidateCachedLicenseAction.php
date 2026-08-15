<?php

namespace App\Filament\Actions;

use App\Models\Church;
use App\Services\AuditLogService;
use App\Services\LicenseKeyService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Heartbeat is client-initiated — the Control Plane cannot push a fresh license
 * into HopeWorks-church. This action invalidates cached license JWTs so the
 * church's *next* heartbeat must obtain a new license reflecting current status.
 */
class InvalidateCachedLicenseAction
{
    public static function make(): Action
    {
        return Action::make('invalidateCachedLicense')
            ->label('Invalidate Cached License')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(fn (?Church $record): bool => $record !== null)
            ->requiresConfirmation()
            ->modalDescription('Cannot force-push a heartbeat (clients pull). This revokes active license JWTs for this church so the next client heartbeat cannot reuse a stale token and must receive current subscription status.')
            ->action(function (
                Church $record,
                LicenseKeyService $licenseKeyService,
                AuditLogService $auditLogService,
            ): void {
                $count = $licenseKeyService->revokeAllForChurch($record);

                $auditLogService->log(
                    actor: auth()->user(),
                    action: 'church.license_cache_invalidated',
                    subject: $record,
                    before: null,
                    after: ['revoked_license_count' => $count],
                    ip: request()?->ip(),
                    highVisibility: true,
                );

                Notification::make()
                    ->title('Cached licenses invalidated')
                    ->body("Revoked {$count} active license JWT(s). The church learns the new status on its next heartbeat — there is no server push.")
                    ->warning()
                    ->send();
            });
    }
}
