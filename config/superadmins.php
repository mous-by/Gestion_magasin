<?php

/*
|--------------------------------------------------------------------------
| Superadmins créés automatiquement (D27)
|--------------------------------------------------------------------------
|
| Lus depuis .env (ignoré par Git) : aucun nom, numéro ni mot de passe dans le dépôt. Créés s'ils n'existent pas, en
| développement comme en hébergement : au lancement de `artisan serve`, après chaque `migrate`, et à la première visite
| de la page de connexion. Voir App\Services\Users\SuperadminBootstrapper.
|
| Hors environnement `local`, le mot de passe n'est que TEMPORAIRE : le compte doit le changer à la première connexion.
|
*/

return [

    'auto_create' => (bool) env('SUPERADMIN_AUTO_CREATE', true),

    'password' => env('SUPERADMIN_PASSWORD'),

    'accounts' => [
        ['name' => env('SUPERADMIN_1_NAME'), 'phone' => env('SUPERADMIN_1_PHONE')],
        ['name' => env('SUPERADMIN_2_NAME'), 'phone' => env('SUPERADMIN_2_PHONE')],
    ],

];
