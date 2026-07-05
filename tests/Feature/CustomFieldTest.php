<?php

namespace Tests\Feature;

use App\Livewire\CustomFieldsForm;
use App\Models\CustomFieldDefinition;
use App\Models\Person;
use App\Services\CustomFieldService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\HopeWorksTestCase;

class CustomFieldTest extends HopeWorksTestCase
{
    public function test_definitions_render_on_custom_fields_form(): void
    {
        $church = $this->createChurch();
        $user = $this->createUser($church);
        $this->actingAs($user);

        CustomFieldDefinition::query()->create([
            'church_id' => $church->id,
            'entity_type' => 'person',
            'field_key' => 'middle_name',
            'label' => 'Middle Name',
            'field_type' => 'text',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        Livewire::test(CustomFieldsForm::class, [
            'entityType' => 'person',
            'churchId' => $church->id,
        ])
            ->assertSee('Middle Name');
    }

    public function test_custom_field_values_save_on_person(): void
    {
        $church = $this->createChurch();
        $user = $this->createUser($church);
        $this->actingAs($user);

        CustomFieldDefinition::query()->create([
            'church_id' => $church->id,
            'entity_type' => 'person',
            'field_key' => 'nickname',
            'label' => 'Nickname',
            'field_type' => 'text',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $person = Person::factory()->create(['church_id' => $church->id]);

        app(CustomFieldService::class)->saveValues('person', $person->id, [
            'nickname' => 'Johnny',
        ], $church->id);

        $this->assertEquals('Johnny', $person->fresh()->getCustomFieldValue('nickname'));
    }

    public function test_required_custom_fields_are_validated(): void
    {
        $church = $this->createChurch();

        CustomFieldDefinition::query()->create([
            'church_id' => $church->id,
            'entity_type' => 'person',
            'field_key' => 'member_id',
            'label' => 'Member ID',
            'field_type' => 'text',
            'is_required' => true,
            'validation_rules' => ['string', 'max:50'],
            'sort_order' => 1,
        ]);

        $this->expectException(ValidationException::class);

        app(CustomFieldService::class)->validate([], 'person', $church->id);
    }

    public function test_valid_custom_field_data_passes_validation(): void
    {
        $church = $this->createChurch();

        CustomFieldDefinition::query()->create([
            'church_id' => $church->id,
            'entity_type' => 'person',
            'field_key' => 'member_id',
            'label' => 'Member ID',
            'field_type' => 'text',
            'is_required' => true,
            'validation_rules' => ['string', 'max:50'],
            'sort_order' => 1,
        ]);

        $result = app(CustomFieldService::class)->validate(
            ['member_id' => 'MEM-001'],
            'person',
            $church->id
        );

        $this->assertEquals('MEM-001', $result['custom_fields']['member_id']);
    }
}
