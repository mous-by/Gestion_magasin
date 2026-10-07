<?php

/*
| Manifeste du module « sorties » (phase 5, cahier §13, §14). Une sortie au-delà du stock est toujours refusée
| (RG04) : la « procédure de régularisation explicitement autorisée » du cahier §13.1 passe par une régularisation
| de stock justifiée (module stock, phase 6), pas par un forçage de la sortie.
*/

return [
    'label' => 'Sorties',

    'permissions' => [
        'view' => 'Voir les sorties et imprimer les bons de sortie',
        'create' => 'Saisir une sortie (brouillon)',
        'update' => 'Modifier ou supprimer un brouillon de sortie',
        'validate' => 'Valider une sortie (diminution du stock)',
        'cancel' => 'Annuler une sortie validée (contre-mouvement, motif obligatoire)',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => [
            'defaults' => ['view', 'create', 'update', 'validate', 'cancel'],
            'optional' => [],
        ],
        'administrateur' => [
            'defaults' => [],
            'optional' => ['view'],
        ],
    ],

    'navigation' => [
        ['label' => 'Sorties', 'icon' => 'bx bx-log-out-circle', 'route' => 'sorties.index', 'permission' => 'sorties.view', 'active' => 'sorties.*', 'order' => 40],
    ],
];
