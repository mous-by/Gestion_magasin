<?php

namespace Database\Seeders;

use App\Services\Users\SuperadminBootstrapper;
use Illuminate\Database\Seeder;

/**
 * Superadmins déclarés dans .env (D27) : même code que l'amorçage automatique (serve, migrate, page de connexion).
 * Aucun compte ni mot de passe dans Git.
 */
class SuperadminSeeder extends Seeder
{
    public function run(SuperadminBootstrapper $bootstrapper): void
    {
        $created = $bootstrapper->ensure();

        foreach ($created as $phone) {
            $this->command?->info("Superadmin créé : connexion avec le numéro {$phone}.");
        }

        if ($created === []) {
            $this->command?->line('Superadmins : déjà créés, ou non configurés dans .env (SUPERADMIN_*).');
        }
    }
}
