<?php

namespace App\Filament\Resources\Synods\Pages;

use App\Filament\Resources\Synods\SynodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSynods extends ListRecords
{
    protected static string $resource = SynodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
