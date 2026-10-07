<?php

/*
|--------------------------------------------------------------------------
| Hiérarchie des rôles (CLAUDE.md §4.2)
|--------------------------------------------------------------------------
|
| Source unique des niveaux et du plafond de rôle. Les Policies passent par
| App\Enums\RoleName, jamais par des comparaisons de chaînes dispersées.
| Personne n'agit sur un compte de niveau supérieur ou égal au sien, sauf le
| superadmin.
|
*/

return [

    'levels' => [
        'superadmin' => 100,
        'administrateur' => 80,
        'magasinier' => 50,
    ],

    // Plafond de rôle : rôles qu'un rôle peut créer et gérer.
    'manages' => [
        'superadmin' => ['superadmin', 'administrateur', 'magasinier'],
        'administrateur' => ['magasinier'],
        'magasinier' => [],
    ],

];
