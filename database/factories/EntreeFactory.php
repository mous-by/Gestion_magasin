<?php

namespace Database\Factories;

use App\Models\Entree;
use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Brouillon d'entrée sans ligne. Pour une entrée complète ou validée, passer par EntreeService (comme l'application).
 *
 * @extends Factory<Entree>
 */
class EntreeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'received_on' => now()->toDateString(),
            'fournisseur_id' => Fournisseur::factory(),
            'delivery_note_number' => 'BL-'.fake()->numerify('####'),
            'order_reference' => null,
            'observation' => null,
            'created_by' => User::factory(),
        ];
    }
}
