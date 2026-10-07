<?php

/*
|--------------------------------------------------------------------------
| Identité de l'application
|--------------------------------------------------------------------------
|
| Le nom est affiché en logo 3D bicolore dans la sidebar et sur la page de connexion :
| `logo.first` en bleu, `logo.second` en orange. Changer de nom = modifier ce fichier et APP_NAME dans .env.
| Nom officiel de l'application : À VALIDER AVEC LE CLIENT (cahier §33). « MagaStock » est une proposition ;
| le dépôt et le dossier restent « Gestion_magasin ».
|
*/

return [

    'name' => env('APP_NAME', 'MagaStock'),

    'logo' => ['first' => 'MAGA', 'second' => 'STOCK'],

    // Mini logo affiché quand la sidebar est repliée.
    'mini' => 'MS',

    'tagline' => 'GESTION DU MAGASIN',

    /*
    | En-tête officiel des documents imprimés (bons, fiches, rapports), relevé sur le bon de sortie de l'Université
    | (carnet rose « RECTORAT — BON DE SORTIE », reçu le 2026-10-07). Modifiable dans .env sans toucher au code.
    | `logo` : image déposée dans public/assets/images/brand/ (logo-universite.png ou .jpg), affichée si présente.
    */
    'structure' => env('BRAND_STRUCTURE', 'Université de Ségou'),

    'service' => env('BRAND_SERVICE', 'Magasin — Comptabilité matière'),

    'ministry' => env('BRAND_MINISTRY', 'Ministère de l\'Enseignement Supérieur et de la Recherche Scientifique'),

    'country' => env('BRAND_COUNTRY', 'République du Mali'),

    'motto' => env('BRAND_MOTTO', 'Un Peuple - Un But - Une Foi'),

    'entity' => env('BRAND_ENTITY', 'Rectorat'),

    'city' => env('BRAND_CITY', 'Ségou'),

    // Mention affichée à la place du nom d'un superadmin pour tous les autres rôles (comptes invisibles).
    'system_actor' => env('BRAND_SYSTEM_ACTOR', 'Administration'),

    'logo_candidates' => ['assets/images/brand/logo-universite.png', 'assets/images/brand/logo-universite.jpg'],

    /*
    | Diaporama de la page de connexion (formulaire à droite, images qui défilent à gauche).
    | `file` : nom de l'image SANS extension, dans public/assets/images/login/ (jpg, jpeg, webp ou png, dans cet ordre).
    | `position` : cadrage de l'image (background-position CSS). Une image absente n'est pas une erreur : dégradé bleu.
    | Images livrées le 2026-10-07 (générées par IA d'après les prompts de CLAUDE.md §7, sans texte ni logo), JPEG
    | 1659 × 948 de 200 à 340 Ko ; slide-4 retournée horizontalement pour garder le sujet hors du formulaire.
    */
    // Dossier des images du diaporama, relatif à public/ (surchargé par les tests).
    'login_images_directory' => 'assets/images/login',

    'login_slides' => [
        ['file' => 'slide-1', 'position' => '30% center', 'icon' => 'bx bx-log-in-circle', 'badge' => 'Entrées', 'title' => 'Chaque livraison enregistrée', 'text' => 'Bordereau, quantités livrées et acceptées : les écarts de livraison sont signalés et conservés.'],
        ['file' => 'slide-2', 'position' => '25% center', 'icon' => 'bx bx-log-out-circle', 'badge' => 'Sorties', 'title' => 'Des sorties sans erreur', 'text' => 'Le stock disponible s\'affiche pendant la saisie ; une sortie supérieure au stock est bloquée.'],
        ['file' => 'slide-3', 'position' => '35% center', 'icon' => 'bx bx-package', 'badge' => 'Stock', 'title' => 'Un stock fiable à tout moment', 'text' => 'Calculé à partir des mouvements validés, avec alertes de seuil et de rupture.'],
        ['file' => 'slide-4', 'position' => '20% center', 'icon' => 'bx bx-file', 'badge' => 'Documents', 'title' => 'Bons et états en un clic', 'text' => 'Bon de sortie, fiche de réception, état de stock et rapports au format PDF.'],
        ['file' => 'slide-5', 'position' => '25% center', 'icon' => 'bx bx-history', 'badge' => 'Traçabilité', 'title' => 'Tout l\'historique', 'text' => 'Chaque mouvement garde sa référence, sa date et son auteur.'],
    ],

];
