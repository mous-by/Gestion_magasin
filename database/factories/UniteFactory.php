<?php

namespace Database\Factories;

use App\Models\Unite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unite>
 */
class UniteFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Unité '.fake()->unique()->word();

        return ['name' => $name, 'symbol' => fake()->unique()->lexify('u???'), 'allows_decimal' => false, 'is_active' => true];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
