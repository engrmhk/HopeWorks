<?php

namespace App\Livewire;

use App\Services\CustomFieldService;
use Livewire\Component;

class CustomFieldsForm extends Component
{
    public string $entityType;

    public ?int $entityId = null;

    public ?int $churchId = null;

    /** @var array<string, mixed> */
    public array $customFields = [];

    public function mount(string $entityType, ?int $entityId = null, ?int $churchId = null): void
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->churchId = $churchId ?? auth()->user()?->church_id;

        if ($entityId) {
            $this->customFields = app(CustomFieldService::class)
                ->getValuesForEntity($entityType, $entityId);
        }
    }

    public function save(): void
    {
        if (! $this->entityId) {
            return;
        }

        app(CustomFieldService::class)->saveValues(
            $this->entityType,
            $this->entityId,
            $this->customFields,
            $this->churchId
        );
    }

    public function render()
    {
        $definitions = app(CustomFieldService::class)
            ->getDefinitionsForEntity($this->entityType, $this->churchId);

        return view('livewire.custom-fields-form', [
            'definitions' => $definitions,
        ]);
    }
}
