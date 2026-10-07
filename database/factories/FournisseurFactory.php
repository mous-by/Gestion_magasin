<?php

namespace Database\Factories;

use App\Models\Fournisseur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fournisseur>
 */
class FournisseurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'address' => fake()->address(),
            'phone' => '+2237'.fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'contact_person' => fake()->name(),
            'admin_references' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
