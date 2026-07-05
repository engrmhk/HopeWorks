<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomFieldService
{
    public function getDefinitionsForEntity(string $entityType, ?int $churchId = null): \Illuminate\Support\Collection
    {
        return CustomFieldDefinition::query()
            ->where('entity_type', $entityType)
            ->when($churchId, fn ($q) => $q->where('church_id', $churchId))
            ->orderBy('sort_order')
            ->get();
    }

    public function getValuesForEntity(string $entityType, int $entityId): array
    {
        $values = CustomFieldValue::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('definition')
            ->get();

        return $values->mapWithKeys(function (CustomFieldValue $value) {
            return [$value->definition->field_key => $value->value];
        })->all();
    }

    public function validate(array $data, string $entityType, ?int $churchId = null): array
    {
        $definitions = $this->getDefinitionsForEntity($entityType, $churchId);
        $rules = [];
        $attributes = [];

        foreach ($definitions as $definition) {
            $key = "custom_fields.{$definition->field_key}";
            $fieldRules = $definition->validation_rules ?? [];

            if ($definition->is_required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $rules[$key] = $fieldRules;
            $attributes[$key] = $definition->label;
        }

        return Validator::make(['custom_fields' => $data], $rules, [], $attributes)->validate();
    }

    public function saveValues(string $entityType, int $entityId, array $data, ?int $churchId = null): void
    {
        $validated = $this->validate($data, $entityType, $churchId);
        $fields = $validated['custom_fields'] ?? [];

        $definitions = $this->getDefinitionsForEntity($entityType, $churchId);

        foreach ($definitions as $definition) {
            if (! array_key_exists($definition->field_key, $fields)) {
                continue;
            }

            CustomFieldValue::query()->updateOrCreate(
                [
                    'custom_field_definition_id' => $definition->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ],
                ['value' => $fields[$definition->field_key]]
            );
        }
    }
}
