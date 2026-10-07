<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Fournisseur;
use App\Models\Service;
use App\Models\Unite;
use Illuminate\Database\Seeder;

/**
 * Catalogue initial RÉEL (tâche R5, cahier §33 « liste initiale des articles ») relevé sur les documents remis le
 * 2026-10-07 : bordereau de livraison de Mamadou A DIALLO (Commerce Général, Ségou) et bon des sorties
 * N°2026-029/US-SP du Service Patrimoine. Utilisable en production : `php artisan db:seed --class=CatalogueInitialSeeder`.
 *
 * Idempotent : un article existant (même référence) n'est jamais modifié ; une catégorie, une unité, un fournisseur
 * ou un service existant (même nom) est réutilisé. Le classement par catégorie, les références (préfixe par
 * catégorie) et les unités sont une PROPOSITION À VALIDER avec le magasinier ; les seuils restent à fixer (§33).
 */
class CatalogueInitialSeeder extends Seeder
{
    /** Catégorie => [préfixe, description]. */
    public const CATEGORIES = [
        'Éclairage' => ['ECL', 'Réglettes, ampoules et tubes'],
        'Câbles et fils' => ['CAB', 'Fils, câbles souples, rouleaux'],
        'Appareillage électrique' => ['APP', 'Disjoncteurs, inverseurs, prises, contacts, rallonges'],
        'Fixation et accessoires' => ['FIX', 'Attaches, chevilles, vis, goulottes'],
        'Ventilation' => ['VEN', 'Ventilateurs'],
        'Piles' => ['PIL', 'Piles et accumulateurs'],
        'Sonorisation' => ['SON', 'Micros et matériel audio'],
    ];

    /** Unités (nom => [symbole, décimales]) : celles du cahier §9 et celles des documents. */
    public const UNITES = [
        'Unité' => ['U', false],
        'Paquet' => ['pqt', false],
        'Rouleau' => ['rlx', false],
        'Mètre' => ['m', true],
        'Carton' => ['ctn', false],
    ];

    /**
     * Articles : [référence, désignation, catégorie, unité, observation]. L'observation garde le libellé d'origine
     * quand il diffère, pour retrouver l'article sur les anciens documents papier.
     */
    public const ARTICLES = [
        ['ECL-001', 'Réglette complète 0,60 m Ingelec LED', 'Éclairage', 'Unité', null],
        ['ECL-002', 'Réglette complète 1,20 m Ingelec LED', 'Éclairage', 'Unité', 'Bon Patrimoine : « Réglette complet, 20 ingelec »'],
        ['ECL-003', 'Ampoule chargeable 30 W à vis', 'Éclairage', 'Unité', null],
        ['ECL-004', 'Ampoule 1,20 m LED Ingelec', 'Éclairage', 'Unité', null],
        ['ECL-005', 'Ampoule 0,60 m LED Ingelec', 'Éclairage', 'Unité', null],
        ['ECL-006', 'Ampoule 0,60 m Ingelec', 'Éclairage', 'Unité', null],
        ['CAB-001', 'Fil câble Diallo 1,5 mm² (rouleau de 30 m)', 'Câbles et fils', 'Rouleau', null],
        ['CAB-002', 'Fil câble Diallo 2,5 mm² (rouleau de 30 m)', 'Câbles et fils', 'Rouleau', null],
        ['CAB-003', 'Câble souple Ingelec 4x6 mm', 'Câbles et fils', 'Mètre', null],
        ['CAB-004', 'Câble souple Ingelec 4x10 mm (rouleau de 30 m)', 'Câbles et fils', 'Rouleau', null],
        ['CAB-005', 'Fil TH 1,5 mm²', 'Câbles et fils', 'Rouleau', null],
        ['CAB-006', 'Fil TH 2,5 mm²', 'Câbles et fils', 'Rouleau', null],
        ['APP-001', 'Inverseur monophasé 63 A', 'Appareillage électrique', 'Unité', null],
        ['APP-002', 'Inverseur triphasé 400 V', 'Appareillage électrique', 'Unité', null],
        ['APP-003', 'Disjoncteur triphasé 4 pôles Ingelec 63 A', 'Appareillage électrique', 'Unité', null],
        ['APP-004', 'Disjoncteur 2 pôles Ingelec 25 A', 'Appareillage électrique', 'Unité', null],
        ['APP-005', 'Disjoncteur triphasé différentiel Ingelec 63 A', 'Appareillage électrique', 'Unité', null],
        ['APP-006', 'Disjoncteur triphasé différentiel Ingelec 40 A', 'Appareillage électrique', 'Unité', null],
        ['APP-007', 'Disjoncteur triphasé différentiel 30-60 A', 'Appareillage électrique', 'Unité', null],
        ['APP-008', 'Dismatic Ingelec (paquet de 10)', 'Appareillage électrique', 'Paquet', null],
        ['APP-009', 'Compact 250 DC', 'Appareillage électrique', 'Paquet', 'Unité à confirmer : « Paquet » sur le bon Patrimoine, sans unité sur le bordereau'],
        ['APP-010', 'Prise de terre étanche', 'Appareillage électrique', 'Unité', null],
        ['APP-011', 'Prise de terre (paquet)', 'Appareillage électrique', 'Paquet', null],
        ['APP-012', 'Fiche mâle Ingelec (paquet)', 'Appareillage électrique', 'Paquet', null],
        ['APP-013', 'Contact simple (paquet de 6)', 'Appareillage électrique', 'Paquet', null],
        ['APP-014', 'Contact va-et-vient (paquet de 6)', 'Appareillage électrique', 'Paquet', null],
        ['APP-015', 'Rallonge 10 m Ingelec', 'Appareillage électrique', 'Unité', null],
        ['APP-016', 'Rallonge 6 trous Ingelec', 'Appareillage électrique', 'Unité', null],
        ['APP-017', 'Boîte ronde', 'Appareillage électrique', 'Unité', null],
        ['FIX-001', 'Attache n°8 (paquet)', 'Fixation et accessoires', 'Paquet', null],
        ['FIX-002', 'Sacretone (paquet)', 'Fixation et accessoires', 'Paquet', null],
        ['FIX-003', 'Goulotte 16/10 avec col (carton de 24)', 'Fixation et accessoires', 'Carton', null],
        ['FIX-004', 'Cheville n°8 (paquet)', 'Fixation et accessoires', 'Paquet', null],
        ['FIX-005', 'Vis n°8 (paquet)', 'Fixation et accessoires', 'Paquet', null],
        ['VEN-001', 'Ventilateur Everal radar (mural)', 'Ventilation', 'Unité', null],
        ['VEN-002', 'Ventilateur TMT gros moteur', 'Ventilation', 'Unité', null],
        ['PIL-001', 'Pile circulaire (paquet)', 'Piles', 'Paquet', null],
        ['PIL-002', 'Pile cubique (paquet)', 'Piles', 'Paquet', null],
        ['SON-001', 'Micro baladeur Shure portée 2 à 3 km', 'Sonorisation', 'Unité', null],
        ['SON-002', 'Micro conférence sans fil Shure grande portée', 'Sonorisation', 'Unité', null],
    ];

    /** Services relevés sur les documents (bon de sortie du carnet, bon du Service Patrimoine). */
    public const SERVICES = [
        ['Rectorat', 'REC'],
        ['Service Patrimoine', 'US-SP'],
        ['Division Équipement', 'DIV-EQ'],
        ['Service des Finances', 'FIN'],
    ];

    public function run(): void
    {
        $categories = [];
        foreach (self::CATEGORIES as $name => [, $description]) {
            $categories[$name] = Category::withTrashed()->firstOrCreate(['name' => $name], ['description' => $description])->id;
        }

        $unites = [];
        foreach (self::UNITES as $name => [$symbol, $decimal]) {
            $unites[$name] = (Unite::withTrashed()->where('name', $name)->orWhere('symbol', $symbol)->first()
                ?? Unite::create(['name' => $name, 'symbol' => $symbol, 'allows_decimal' => $decimal]))->id;
        }

        $created = 0;
        foreach (self::ARTICLES as [$reference, $designation, $category, $unite, $observation]) {
            $article = Article::withTrashed()->firstOrCreate(['reference' => $reference], [
                'designation' => $designation, 'category_id' => $categories[$category], 'unite_id' => $unites[$unite], 'observation' => $observation,
            ]);
            $created += (int) $article->wasRecentlyCreated;
        }

        Fournisseur::withTrashed()->firstOrCreate(['name' => 'Mamadou A DIALLO — Commerce Général'], [
            'address' => 'Face à l\'ex-gare routière de Ségou, BP 441',
            'phone' => '66 72 38 53',
            'contact_person' => 'Mamadou A DIALLO',
            'admin_references' => 'N° fiscal 04 1001227L · RC 0190',
        ]);

        foreach (self::SERVICES as [$name, $code]) {
            Service::withTrashed()->firstOrCreate(['name' => $name], ['code' => $code]);
        }

        $this->command?->info("Catalogue initial : {$created} article(s) créé(s) sur ".count(self::ARTICLES).'.');
    }
}
