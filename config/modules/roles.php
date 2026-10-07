<?php

return [
    'label' => 'Rôles',

    'permissions' => [
        'view' => 'Consulter le catalogue des rôles',
    ],

    // Réservée au superadmin : jamais attribuable à un autre rôle.
    'reserved' => ['view'],

    'roles' => [],

    // Pas d'entrée ici : le point d'entrée « Paramètres » est calculé dynamiquement par ModuleServiceProvider
    // (App\Support\ModuleRegistry::firstAccessibleSettingsItem), pas déclaré statiquement — il doit s'adapter à
    // qui peut voir quoi (Rôles est réservé au superadmin, mais Utilisateurs ne l'est pas).
    'navigation' => [],
];
