<?php

/*
| Module « permissions » : les deux voies (D23). `create` = créer une permission depuis l'interface (source = custom) ;
| `assign` = attribuer des permissions à des utilisateurs (exceptions) ou à des rôles. Toutes réservées au superadmin.
*/

return [
    'label' => 'Permissions',

    'permissions' => [
        'view' => 'Consulter le référentiel des permissions',
        'create' => 'Créer une permission',
        'assign' => 'Attribuer des permissions',
    ],

    'reserved' => ['view', 'create', 'assign'],

    'roles' => [],

    // Pas d'entrée dans la sidebar : Permissions et Attribution s'ouvrent depuis le menu Paramètres.
    'navigation' => [],
];
