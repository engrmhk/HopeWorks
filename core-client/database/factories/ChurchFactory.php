<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Church>
 */
class ChurchFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Church';

        return [
            'parent_id' => null,
            'name' => $name,
            'synod_instance' => false,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'is_active' => true,
            'settings' => [],
        ];
    }

    public function synod(): static
    {
        return $this->state(fn (array $attributes) => [
            'synod_instance' => true,
        ]);
    }
}
