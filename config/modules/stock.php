<?php

/*
| Manifeste du module « stock » (phase 6, cahier §15, §16). État du stock, fiche de mouvement d'un article et
| régularisations justifiées (RG11). La régularisation est donnée au magasinier (cahier §31 « gestion d'une erreur
| ou d'une régularisation ») — qui peut régulariser et avec quels motifs : À VALIDER (cahier §33).
*/

return [
    'label' => 'Stock et mouvements',

    'permissions' => [
        'view' => 'Voir l\'état du stock et l\'historique des mouvements',
        'regularize' => 'Régulariser un stock (motif obligatoire)',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => [
            'defaults' => ['view', 'regularize'],
            'optional' => [],
        ],
        'administrateur' => [
            'defaults' => [],
            'optional' => ['view'],
        ],
    ],

    'navigation' => [
        ['label' => 'Stock', 'icon' => 'bx bx-transfer-alt', 'route' => 'stock.index', 'permission' => 'stock.view', 'active' => 'stock.*', 'order' => 50],
    ],
];
