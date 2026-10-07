<?php

/*
| Manifeste du module « documents » : paramètres des documents imprimés (en-tête officiel, logo, signataires,
| numérotation), modifiables depuis l'interface (Paramètres → Documents). Réglage confié à l'administrateur
| technique (cahier §6 « paramètres ») ; le magasinier peut le recevoir en exception.
*/

return [
    'label' => 'Paramètres des documents',

    'permissions' => [
        'manage' => 'Modifier l\'en-tête, le logo, les signataires et la numérotation des documents',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['manage'], 'optional' => []],
        'magasinier' => ['defaults' => [], 'optional' => ['manage']],
    ],

    // Pas d'entrée de sidebar : l'écran s'ouvre depuis le sous-menu Paramètres (ModuleRegistry::settingsItems).
    'navigation' => [],
];
