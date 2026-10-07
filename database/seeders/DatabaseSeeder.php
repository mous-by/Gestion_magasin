<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Rôles et permissions (RoleSeeder), puis les superadmins déclarés dans .env (SuperadminSeeder, D27). Aucun
     * compte ni mot de passe dans Git. Puis les unités citées par le cahier (UniteSeeder) ; `php artisan app:create-superadmin` reste disponible (mot de passe interactif).
     */
    public function run(): void
    {
        $this->call([RoleSeeder::class, SuperadminSeeder::class, UniteSeeder::class]);
    }
}
