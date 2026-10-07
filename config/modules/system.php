<?php

return [
    'label' => 'Système',

    'permissions' => [
        'view' => 'Consulter la page système',
        'maintain' => 'Lancer les actions de maintenance',
    ],

    'reserved' => ['view', 'maintain'],

    'roles' => [],

    // Pas d'entrée de sidebar : l'écran s'ouvre depuis le sous-menu Paramètres (configuration/_menu.blade.php).
    'navigation' => [],
];
