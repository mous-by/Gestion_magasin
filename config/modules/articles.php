<?php

/*
| Manifeste du module « articles » (phase 3, cahier §9). Catalogue des articles et ses référentiels (catégories,
| unités : permission `manage_referentiels`). L'administrateur technique peut recevoir la consultation en exception.
*/

return [
    'label' => 'Articles',

    'permissions' => [
        'view' => 'Voir le catalogue des articles',
        'create' => 'Créer un article',
        'update' => 'Modifier un article (et l\'activer ou le désactiver)',
        'delete' => 'Supprimer un article',
        'manage_referentiels' => 'Gérer les catégories et les unités',
        'import' => 'Importer des articles depuis un fichier Excel (et télécharger le modèle)',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => [
            'defaults' => ['view', 'create', 'update', 'manage_referentiels', 'import'],
            'optional' => ['delete'],
        ],
        'administrateur' => [
            'defaults' => [],
            'optional' => ['view'],
        ],
    ],

    'navigation' => [
        ['label' => 'Articles', 'icon' => 'bx bx-package', 'route' => 'articles.index', 'permission' => 'articles.view', 'active' => ['articles.*', 'categories.*', 'unites.*', 'services.*'], 'order' => 10],
    ],
];
