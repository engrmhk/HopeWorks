<?php

namespace Tests\Feature;

use App\Models\Person;
use Tests\HopeWorksTestCase;

class ChurchScopeTest extends HopeWorksTestCase
{
    public function test_church_admin_cannot_access_another_churchs_people(): void
    {
        $churchA = $this->createChurch(['name' => 'Church A']);
        $churchB = $this->createChurch(['name' => 'Church B']);

        $adminA = $this->createUser($churchA, 'Church Admin');
        $personB = Person::factory()->create(['church_id' => $churchB->id]);

        $this->actingAs($adminA);

        $visiblePeople = Person::query()->pluck('id')->all();

        $this->assertNotContains($personB->id, $visiblePeople);
    }

    public function test_church_admin_can_access_own_church_people(): void
    {
        $church = $this->createChurch();
        $admin = $this->createUser($church, 'Church Admin');
        $person = Person::factory()->create(['church_id' => $church->id]);

        $this->actingAs($admin);

        $this->assertTrue(Person::query()->whereKey($person->id)->exists());
    }

    public function test_synod_admin_can_access_all_churches_people(): void
    {
        $synod = $this->createChurch(['synod_instance' => true]);
        $churchA = $this->createChurch(['parent_id' => $synod->id]);
        $churchB = $this->createChurch(['parent_id' => $synod->id]);

        $synodAdmin = $this->createUser($synod, 'Synod Admin');
        $personA = Person::factory()->create(['church_id' => $churchA->id]);
        $personB = Person::factory()->create(['church_id' => $churchB->id]);

        $this->actingAs($synodAdmin);

        $visibleIds = Person::query()->pluck('id')->all();

        $this->assertContains($personA->id, $visibleIds);
        $this->assertContains($personB->id, $visibleIds);
    }

    public function test_church_policy_prevents_cross_church_access(): void
    {
        $churchA = $this->createChurch();
        $churchB = $this->createChurch();
        $adminA = $this->createUser($churchA, 'Church Admin');

        $this->actingAs($adminA);

        $this->assertTrue($adminA->can('view', $churchA));
        $this->assertFalse($adminA->can('view', $churchB));
    }
}
