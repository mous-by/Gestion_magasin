<?php

/*
| Manifeste du module « aide » : guide d'utilisation en ligne (livrable « documentation utilisateur », cahier §30),
| organisé selon les thèmes de la formation du magasinier (cahier §31).
*/

return [
    'label' => 'Aide',

    'permissions' => [
        'view' => 'Consulter le guide d\'utilisation',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => ['defaults' => ['view'], 'optional' => []],
        'administrateur' => ['defaults' => ['view'], 'optional' => []],
    ],

    'navigation' => [
        ['label' => 'Aide', 'icon' => 'bx bx-help-circle', 'route' => 'aide.index', 'permission' => 'aide.view', 'active' => 'aide.*', 'order' => 850, 'position' => 'bottom'],
    ],
];
