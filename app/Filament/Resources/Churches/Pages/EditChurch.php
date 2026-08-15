<?php

namespace App\Filament\Resources\Churches\Pages;

use App\Filament\Actions\ChangeAffiliationAction;
use App\Filament\Actions\ExportOffboardingAction;
use App\Filament\Actions\ForceStatusCheckAction;
use App\Filament\Actions\GenerateInstanceApiKeyAction;
use App\Filament\Actions\ImpersonateUserAction;
use App\Filament\Actions\InvalidateCachedLicenseAction;
use App\Filament\Actions\RecordManualPaymentAction;
use App\Filament\Actions\RevokeInstanceApiKeyAction;
use App\Filament\Actions\RotateInstanceApiKeyAction;
use App\Filament\Resources\Churches\ChurchResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditChurch extends EditRecord
{
    protected static string $resource = ChurchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                GenerateInstanceApiKeyAction::make(),
                RotateInstanceApiKeyAction::make(),
                RevokeInstanceApiKeyAction::make(),
            ])
                ->label('API Key')
                ->icon('heroicon-m-key')
                ->color('warning')
                ->button()
                ->dropdownWidth(Width::ExtraSmall),

            ActionGroup::make([
                RecordManualPaymentAction::make(),
                ForceStatusCheckAction::make(),
                InvalidateCachedLicenseAction::make(),
            ])
                ->label('Billing & License')
                ->icon('heroicon-m-credit-card')
                ->color('success')
                ->button()
                ->dropdownWidth(Width::Small),

            ActionGroup::make([
                ChangeAffiliationAction::make(),
                ImpersonateUserAction::make(),
                ExportOffboardingAction::make(),
            ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->button()
                ->dropdownWidth(Width::Small),

            DeleteAction::make(),
        ];
    }
}
