<?php

namespace App\Filament\Resources\CustomFieldDefinitions\Pages;

use App\Filament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomFieldDefinition extends CreateRecord
{
    protected static string $resource = CustomFieldDefinitionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['church_id'] = auth()->user()?->church_id;

        return $data;
    }
}
