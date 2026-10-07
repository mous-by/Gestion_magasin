<?php

namespace Database\Seeders;

use App\Services\Permissions\PermissionSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Rôles seulement (CLAUDE.md §4) : aucun compte, aucun mot de passe. Crée aussi les permissions des manifestes
 * et les défauts par rôle (même code que `php artisan permissions:sync`).
 */
class RoleSeeder extends Seeder
{
    public function run(PermissionSynchronizer $synchronizer): void
    {
        $synchronizer->sync();
    }
}
