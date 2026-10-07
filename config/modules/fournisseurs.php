<?php

/*
| Manifeste du module « fournisseurs » (phase 3, cahier §10).
*/

return [
    'label' => 'Fournisseurs',

    'permissions' => [
        'view' => 'Voir les fournisseurs',
        'create' => 'Créer un fournisseur',
        'update' => 'Modifier un fournisseur (et l\'activer ou le désactiver)',
        'delete' => 'Supprimer un fournisseur',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => [
            'defaults' => ['view', 'create', 'update'],
            'optional' => ['delete'],
        ],
        'administrateur' => [
            'defaults' => [],
            'optional' => ['view'],
        ],
    ],

    'navigation' => [
        ['label' => 'Fournisseurs', 'icon' => 'bx bx-store', 'route' => 'fournisseurs.index', 'permission' => 'fournisseurs.view', 'active' => 'fournisseurs.*', 'order' => 20],
    ],
];
