<?php

namespace App\Models\Concerns;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasCustomFields
{
    abstract public function getCustomFieldEntityType(): string;

    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'entity', 'entity_type', 'entity_id');
    }

    public function getCustomFieldDefinitions(): Collection
    {
        return CustomFieldDefinition::query()
            ->where('entity_type', $this->getCustomFieldEntityType())
            ->when($this->church_id, fn ($q) => $q->where('church_id', $this->church_id))
            ->orderBy('sort_order')
            ->get();
    }

    public function getCustomFieldValue(string $fieldKey): ?string
    {
        $definition = $this->getCustomFieldDefinitions()->firstWhere('field_key', $fieldKey);

        if (! $definition) {
            return null;
        }

        return CustomFieldValue::query()
            ->where('custom_field_definition_id', $definition->id)
            ->where('entity_type', $this->getCustomFieldEntityType())
            ->where('entity_id', $this->id)
            ->value('value');
    }

    public function setCustomFieldValues(array $values): void
    {
        foreach ($values as $fieldKey => $value) {
            $definition = $this->getCustomFieldDefinitions()->firstWhere('field_key', $fieldKey);

            if (! $definition) {
                continue;
            }

            CustomFieldValue::query()->updateOrCreate(
                [
                    'custom_field_definition_id' => $definition->id,
                    'entity_type' => $this->getCustomFieldEntityType(),
                    'entity_id' => $this->id,
                ],
                ['value' => $value]
            );
        }
    }
}
