<?php

/*
| Manifeste du module « rapports » (phase 7, cahier §17, §18) : état de stock, rapports des entrées, des sorties et
| des écarts, en PDF ou en fichier tableur (CSV lisible par Excel, cahier §23 « éventuellement Excel »).
*/

return [
    'label' => 'Rapports',

    'permissions' => [
        'view' => 'Consulter et imprimer les rapports (PDF)',
        'export' => 'Exporter les rapports vers un tableur (CSV / Excel)',
    ],

    'reserved' => [],

    'roles' => [
        'magasinier' => [
            'defaults' => ['view', 'export'],
            'optional' => [],
        ],
        'administrateur' => [
            'defaults' => [],
            'optional' => ['view', 'export'],
        ],
    ],

    'navigation' => [
        ['label' => 'Rapports', 'icon' => 'bx bx-bar-chart-alt-2', 'route' => 'rapports.index', 'permission' => 'rapports.view', 'active' => 'rapports.*', 'order' => 60],
    ],
];
