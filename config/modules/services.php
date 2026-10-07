<?php

/*
| Manifeste du module « services » (phase 3, cahier §13, §17) : services bénéficiaires des sorties. Pas d'entrée
| de menu propre : l'écran s'ouvre depuis les onglets des référentiels (config/referentiels.php).
*/

return [
    'label' => 'Services bénéficiaires',

    'permissions' => [
        'view' => 'Voir les services bénéficiaires',
        'create' => 'Créer un service bénéficiaire',
        'update' => 'Modifier un service bénéficiaire',
        'delete' => 'Supprimer un service bénéficiaire',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => [
            'defaults' => ['view', 'create', 'update'],
            'optional' => ['delete'],
        ],
        'administrateur' => [
            'defaults' => [],
            'optional' => ['view', 'create', 'update'],
        ],
    ],

    'navigation' => [],
];
