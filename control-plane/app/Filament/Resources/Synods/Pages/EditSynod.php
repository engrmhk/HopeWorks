<?php

namespace App\Filament\Resources\Synods\Pages;

use App\Filament\Resources\Synods\SynodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSynod extends EditRecord
{
    protected static string $resource = SynodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
