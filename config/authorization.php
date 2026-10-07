<?php

/*
|--------------------------------------------------------------------------
| Règles d'autorisation propres à Gestion_magasin (D6, D23)
|--------------------------------------------------------------------------
|
| Les permissions ont deux voies : les manifestes config/modules/*.php
| (source = manifest) et la création par le superadmin depuis l'interface
| (source = custom). Ces règles encadrent la seconde voie.
|
*/

return [

    // Modules techniques : leurs permissions sont réservées au superadmin et
    // ne peuvent pas être créées depuis l'interface.
    'reserved_modules' => ['system', 'roles', 'permissions', 'audit'],

    // Format d'une permission : module.action, minuscules, chiffres et tirets bas.
    'name_pattern' => '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/',

    'name_max_length' => 150,

    // Indicatif appliqué aux numéros locaux de 8 chiffres saisis à la connexion (D26) : 223 = Mali.
    'default_country_code' => '223',

];
