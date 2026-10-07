<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Article;
use App\Models\Entree;
use App\Models\Fournisseur;
use App\Models\Service;
use App\Models\User;
use App\Services\Entrees\EntreeService;
use App\Services\Sorties\SortieService;
use Illuminate\Database\Seeder;

/**
 * Données de DÉMONSTRATION (CLAUDE.md §10, T1) : jamais en production. Rejoue, par les services de l'application
 * (comme une vraie saisie), les documents réels remis le 2026-10-07 :
 *  1. l'entrée du bordereau de livraison de Mamadou A DIALLO (35 articles) ;
 *  2. la sortie du bon N°2026-029/US-SP du Service Patrimoine du 02/10/2026 (articles livrés) ;
 *  3. un brouillon de sortie pour les 5 articles du même bon jamais livrés : sa validation est bloquée (RG04).
 * Lancement : `php artisan db:seed --class=DemoSeeder` (idempotent : rien n'est rejoué si une entrée existe).
 */
class DemoSeeder extends Seeder
{
    /** Bordereau de livraison (référence => quantité livrée). Toutes acceptées, sauf les piles cubiques. */
    private const BORDEREAU = [
        'ECL-001' => 40, 'ECL-002' => 80, 'ECL-003' => 25, 'APP-001' => 5, 'VEN-001' => 3, 'VEN-002' => 3,
        'CAB-001' => 2, 'CAB-002' => 2, 'CAB-003' => 50, 'CAB-004' => 1, 'FIX-001' => 3, 'APP-012' => 3,
        'FIX-002' => 3, 'APP-010' => 25, 'APP-011' => 4, 'FIX-003' => 1, 'APP-015' => 10, 'APP-016' => 15,
        'APP-002' => 2, 'ECL-004' => 25, 'ECL-005' => 25, 'ECL-006' => 25, 'APP-009' => 4, 'APP-003' => 3,
        'APP-004' => 3, 'APP-005' => 5, 'APP-006' => 5, 'APP-007' => 5, 'APP-008' => 10, 'PIL-001' => 7,
        'PIL-002' => 47, 'SON-001' => 3, 'SON-002' => 2, 'APP-013' => 8, 'APP-014' => 5,
    ];

    /** Bon des sorties N°2026-029/US-SP (référence => quantité), dans l'ordre du bon. */
    private const BON_PATRIMOINE = [
        'ECL-002' => 40, 'ECL-003' => 10, 'VEN-001' => 3, 'VEN-002' => 3, 'FIX-002' => 1, 'APP-011' => 2,
        'ECL-004' => 15, 'ECL-005' => 10, 'ECL-006' => 10, 'APP-009' => 2, 'APP-013' => 2, 'CAB-005' => 1,
        'CAB-006' => 1, 'APP-017' => 10, 'APP-010' => 10, 'CAB-001' => 1, 'CAB-002' => 1, 'FIX-004' => 2,
        'FIX-005' => 2, 'FIX-001' => 1, 'APP-008' => 3, 'CAB-003' => 50, 'APP-003' => 2,
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder ignoré en production.');

            return;
        }

        $this->call([UniteSeeder::class, CatalogueInitialSeeder::class]);

        $author = User::role(RoleName::Superadmin->value)->orderBy('id')->first();
        if ($author === null || Entree::exists()) {
            $this->command?->info($author === null ? 'Opérations de démonstration ignorées : aucun superadmin.' : 'Opérations de démonstration déjà présentes.');

            return;
        }

        $id = fn (string $reference) => Article::where('reference', $reference)->value('id');
        $entrees = app(EntreeService::class);
        $sorties = app(SortieService::class);

        // 1. Réception du bordereau. Piles cubiques : annotation « 20 sale + 27 » lue comme 20 paquets refusés
        //    (sales) et 27 acceptés — interprétation à confirmer avec le magasinier.
        $lines = [];
        foreach (self::BORDEREAU as $reference => $quantity) {
            $line = ['article_id' => $id($reference), 'delivered_qty' => $quantity, 'accepted_qty' => $quantity];
            if ($reference === 'PIL-002') {
                $line = ['accepted_qty' => 27, 'rejection_reason' => 'non_conforme', 'observation' => '20 paquets sales refusés (annotation « 20 sale + 27 »)'] + $line;
            }
            $lines[] = $line;
        }
        $entrees->validate($author, $entrees->saveDraft($author, [
            'received_on' => '2026-10-02',
            'fournisseur_id' => Fournisseur::where('name', 'Mamadou A DIALLO — Commerce Général')->value('id'),
            'delivery_note_number' => 'Bordereau Mamadou A DIALLO',
            'observation' => 'Bordereau de livraison « Doit : Université de Ségou », contrôlé ligne par ligne (mentions OK).',
            'lines' => $lines,
        ]));

        // 2. Sortie du bon Patrimoine : seuls les articles en stock passent ; les autres restent en brouillon.
        $available = [];
        $missing = [];
        foreach (self::BON_PATRIMOINE as $reference => $quantity) {
            $article = Article::where('reference', $reference)->first();
            $line = ['article_id' => $article->id, 'quantity' => $quantity];
            (float) $article->stock_quantity >= $quantity ? $available[] = $line : $missing[] = $line;
        }

        $header = [
            'issued_on' => '2026-10-02',
            'requester_name' => 'M. Mamadou SYLLA, Chef de Division Équipement',
            'service_id' => Service::where('name', 'Service Patrimoine')->value('id'),
            'reason' => 'Sortie des équipements d\'électricité',
            'paper_reference' => '2026-029/US-SP',
        ];
        $sorties->validate($author, $sorties->saveDraft($author, $header + ['lines' => $available]));
        $sorties->saveDraft($author, ['observation' => 'Articles du bon 2026-029/US-SP non livrés par le fournisseur : en attente de stock.'] + $header + ['lines' => $missing]);

        $this->command?->info('Démonstration : bordereau Mamadou A DIALLO validé ('.count($lines).' articles), bon 2026-029/US-SP sorti ('.count($available).' articles), brouillon des '.count($missing).' articles non livrés.');
    }
}
