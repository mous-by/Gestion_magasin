<?php

/*
|--------------------------------------------------------------------------
| Numérotation des documents (cahier §19, RG10)
|--------------------------------------------------------------------------
|
| Proposition du cahier : ENT-2026-0001, SOR-2026-0001, REG-2026-0001 (format par défaut). La nomenclature
| officielle reste À VALIDER avec l'administration (§33) : le Service Patrimoine numérote par exemple ses bons
| « 2026-029/US-SP » ; le même style s'obtient avec DOCUMENT_NUMBER_FORMAT="{year}-{number}/US-{prefix}" et
| DOCUMENT_NUMBER_PADDING=3. Jetons : {prefix}, {year}, {number}. Le compteur repart à 1 chaque année.
| Changer le format en cours d'année ne renumérote pas les documents déjà validés.
|
*/

return [

    'number_format' => env('DOCUMENT_NUMBER_FORMAT', '{prefix}-{year}-{number}'),

    'number_padding' => (int) env('DOCUMENT_NUMBER_PADDING', 4),

    // Préfixe par type de document (repris dans {prefix}).
    'prefixes' => [
        'entree' => env('DOCUMENT_PREFIX_ENTREE', 'ENT'),
        'sortie' => env('DOCUMENT_PREFIX_SORTIE', 'SOR'),
        'regularisation' => env('DOCUMENT_PREFIX_REGULARISATION', 'REG'),
    ],

];
