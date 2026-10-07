<?php

/*
|--------------------------------------------------------------------------
| Évolutions prévues, NON implémentées (D25, cahier §27)
|--------------------------------------------------------------------------
|
| Les six modules de la V1 sont livrés (articles, fournisseurs, entrées, sorties, stock, rapports). Cette liste
| montre, dans la sidebar et sur une fiche, ce que la base technique doit permettre d'ajouter ensuite, sans
| l'imposer à la V1 (cahier §5.2, §27, §32). Aucune permission, table ni route n'existe pour ces modules.
| Une entrée disparaît dès que le manifeste config/modules/<clé>.php existe.
|
| `roles` : rôles qui verront ce module (superadmin et administrateur voient tout). `cahier` : où le cahier en
| parle. `open` : décisions à prendre avant de le construire.
|
*/

return [

    'inventaires' => [
        'label' => 'Inventaires physiques',
        'icon' => 'bx bx-clipboard',
        'order' => 300,
        'roles' => ['magasinier'],
        'summary' => 'Comptage physique du magasin (numéro INV-AAAA-NNNN, cahier §19) : feuilles de comptage par emplacement ou catégorie, écarts entre stock calculé et stock compté, validation qui produit les régularisations correspondantes.',
        'cahier' => '§18 (prévu en évolution) · §19 · §27',
        'open' => ['Fréquence et périmètre des inventaires.', 'Qui valide les écarts d\'inventaire.'],
    ],

    'demandes' => [
        'label' => 'Demandes de matériel',
        'icon' => 'bx bx-message-square-add',
        'order' => 310,
        'roles' => ['magasinier'],
        'summary' => 'Les services saisissent eux-mêmes leurs demandes ; le magasinier les transforme en sorties (le module Sorties sait déjà produire le bon).',
        'cahier' => '§25 « Demande de sortie » · §27',
        'open' => ['Comptes pour les services (accès des autres responsables).', 'Circuit de validation des demandes.'],
    ],

    'validations' => [
        'label' => 'Workflow de validation',
        'icon' => 'bx bx-git-branch',
        'order' => 320,
        'roles' => [],
        'summary' => 'Visas administratifs (Chef du Service Patrimoine, Finances) avant certaines sorties ou entrées, hors périmètre V1.',
        'cahier' => '§5.2 · §27',
        'open' => ['Quels documents exigent un visa, et de qui.', 'Seuils (montants, quantités) déclenchant un visa.'],
    ],

    'affectations' => [
        'label' => 'Affectations',
        'icon' => 'bx bx-door-open',
        'order' => 330,
        'roles' => [],
        'summary' => 'Affectation des équipements aux services ou aux salles, avec fiche d\'affectation (prévue en évolution au cahier §18).',
        'cahier' => '§18 · §27',
        'open' => ['Liste des salles et des services.', 'Modèle de la fiche d\'affectation.'],
    ],

    'patrimoine' => [
        'label' => 'Patrimoine',
        'icon' => 'bx bx-building',
        'order' => 340,
        'roles' => [],
        'summary' => 'Gestion complète du patrimoine : équipements individualisés avec numéro d\'inventaire, suivi de leur état et de leur localisation.',
        'cahier' => '§5.2 · §27',
        'open' => ['Nomenclature des numéros d\'inventaire.', 'Articles concernés (consommables exclus ?).'],
    ],

    'qr-code' => [
        'label' => 'QR codes',
        'icon' => 'bx bx-qr',
        'order' => 350,
        'roles' => [],
        'summary' => 'Étiquettes QR sur les articles et les équipements pour retrouver leur fiche et accélérer les entrées, sorties et inventaires.',
        'cahier' => '§27 · §32',
        'open' => ['Matériel d\'impression des étiquettes.', 'Lecture depuis un téléphone (application mobile, §27).'],
    ],

];
