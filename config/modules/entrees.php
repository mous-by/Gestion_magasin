<?php

/*
| Manifeste du module « entrees » (phase 4, cahier §11, §12, §25). Saisie en brouillon (`create`, `update`),
| validation qui met le stock à jour (`validate`), annulation par contre-mouvement avec motif (`cancel`, RG05, RG11).
| L'annulation est donnée au magasinier, qui gère ses erreurs (cahier §31) — À VALIDER avec le client (§33).
*/

return [
    'label' => 'Entrées',

    'permissions' => [
        'view' => 'Voir les entrées et imprimer les fiches de réception',
        'create' => 'Saisir une entrée (brouillon)',
        'update' => 'Modifier ou supprimer un brouillon d\'entrée',
        'validate' => 'Valider une entrée (mise à jour du stock)',
        'cancel' => 'Annuler une entrée validée (contre-mouvement, motif obligatoire)',
        'resolve' => 'Traiter un écart de réception (enregistrer la suite donnée)',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => [
            'defaults' => ['view', 'create', 'update', 'validate', 'cancel', 'resolve'],
            'optional' => [],
        ],
        'administrateur' => [
            'defaults' => [],
            'optional' => ['view'],
        ],
    ],

    'navigation' => [
        ['label' => 'Entrées', 'icon' => 'bx bx-log-in-circle', 'route' => 'entrees.index', 'permission' => 'entrees.view', 'active' => 'entrees.*', 'order' => 30],
    ],
];
