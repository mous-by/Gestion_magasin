<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Sortie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Brouillon de sortie sans ligne. Pour une sortie complète ou validée, passer par SortieService.
 *
 * @extends Factory<Sortie>
 */
class SortieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issued_on' => now()->toDateString(),
            'requester_name' => fake()->name(),
            'service_id' => Service::factory(),
            'reason' => 'Besoin du service',
            'created_by' => User::factory(),
        ];
    }
}
