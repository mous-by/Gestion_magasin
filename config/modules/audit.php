<?php

return [
    'label' => 'Audit',

    'permissions' => [
        'view' => 'Consulter le journal d\'audit',
    ],

    'reserved' => ['view'],

    'roles' => [],

    // Pas d'entrée de sidebar : l'écran s'ouvre depuis le sous-menu Paramètres (configuration/_menu.blade.php).
    'navigation' => [],
];
