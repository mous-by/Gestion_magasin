<?php

/*
|--------------------------------------------------------------------------
| Onglets des référentiels (phase 3)
|--------------------------------------------------------------------------
|
| Une seule entrée de menu (« Articles ») mène aux référentiels ; ces onglets relient les écrans voisins
| (partials/referentiels-tabs). Chaque onglet n'apparaît que si sa route existe et que l'utilisateur a la permission.
|
*/

return [

    'tabs' => [
        ['label' => 'Articles', 'icon' => 'bx bx-package', 'route' => 'articles.index', 'permission' => 'articles.view', 'active' => 'articles.*'],
        ['label' => 'Catégories', 'icon' => 'bx bx-category', 'route' => 'categories.index', 'permission' => 'articles.manage_referentiels', 'active' => 'categories.*'],
        ['label' => 'Unités', 'icon' => 'bx bx-ruler', 'route' => 'unites.index', 'permission' => 'articles.manage_referentiels', 'active' => 'unites.*'],
        ['label' => 'Services bénéficiaires', 'icon' => 'bx bx-buildings', 'route' => 'services.index', 'permission' => 'services.view', 'active' => 'services.*'],
    ],

];
