<?php

namespace App\Filament\Resources\CustomFieldDefinitions\Pages;

use App\Filament\Resources\CustomFieldDefinitions\CustomFieldDefinitionResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomFieldDefinitions extends ListRecords
{
    protected static string $resource = CustomFieldDefinitionResource::class;
}
