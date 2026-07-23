<?php

namespace App\Filament\Resources\Churches\Pages;

use App\Filament\Actions\ChangeAffiliationAction;
use App\Filament\Actions\ExportOffboardingAction;
use App\Filament\Actions\GenerateInstanceApiKeyAction;
use App\Filament\Actions\ImpersonateUserAction;
use App\Filament\Actions\RecordManualPaymentAction;
use App\Filament\Actions\RevokeInstanceApiKeyAction;
use App\Filament\Actions\RotateInstanceApiKeyAction;
use App\Filament\Resources\Churches\ChurchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChurch extends EditRecord
{
    protected static string $resource = ChurchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GenerateInstanceApiKeyAction::make(),
            RotateInstanceApiKeyAction::make(),
            RevokeInstanceApiKeyAction::make(),
            RecordManualPaymentAction::make(),
            ChangeAffiliationAction::make(),
            ImpersonateUserAction::make(),
            ExportOffboardingAction::make(),
            DeleteAction::make(),
        ];
    }
}
