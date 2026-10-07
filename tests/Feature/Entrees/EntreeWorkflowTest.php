<?php

namespace Tests\Feature\Entrees;

use App\Enums\DocumentStatus;
use App\Enums\MouvementType;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Entree;
use App\Models\Fournisseur;
use App\Models\MouvementStock;
use App\Models\Unite;
use App\Models\User;
use App\Services\Documents\DocumentNumberService;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Entrées / réceptions (phase 4, cahier §11, §12, §25) : brouillon, écarts documentés (RG08), validation qui met le
 * stock à jour selon la quantité acceptée (RG02), numérotation unique (RG10), mouvements tracés et non modifiables
 * (RG05, RG06), annulation par contre-mouvement. Critères de recette 11 à 14.
 */
class EntreeWorkflowTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $magasinier;

    private Fournisseur $fournisseur;

    private Article $reglette;

    private Article $ampoule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create(['name' => 'Awa Magasin']);
        $this->fournisseur = Fournisseur::factory()->create(['name' => 'Sahel Équipements']);
        $unite = Unite::factory()->create(['symbol' => 'U']);
        $this->reglette = Article::factory()->create(['reference' => 'ELEC-0001', 'designation' => 'Réglette 1,20 m LED', 'unite_id' => $unite->id]);
        $this->ampoule = Article::factory()->create(['reference' => 'ELEC-0002', 'designation' => 'Ampoule 30W', 'unite_id' => $unite->id]);
    }

    /** Livraison de l'Annexe A : 80 réglettes conformes, 28 ampoules livrées sur 30 prévues dont 3 cassées. */
    private function payload(string $action = 'draft', array $overrides = []): array
    {
        return array_replace_recursive([
            'action' => $action,
            'received_on' => '2026-10-02',
            'fournisseur_id' => $this->fournisseur->id,
            'delivery_note_number' => 'BL-0954',
            'lines' => [
                ['article_id' => $this->reglette->id, 'expected_qty' => '80', 'delivered_qty' => '80', 'accepted_qty' => '80'],
                ['article_id' => $this->ampoule->id, 'expected_qty' => '30', 'delivered_qty' => '28', 'accepted_qty' => '25', 'rejection_reason' => 'endommage', 'observation' => '2 manquantes, 3 cassées'],
            ],
        ], $overrides);
    }

    private function stock(Article $article): float
    {
        return (float) $article->fresh()->stock_quantity;
    }

    public function test_the_screens_follow_the_theme_and_require_their_permissions(): void
    {
        $this->get('/entrees')->assertRedirect('/login');
        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin)->get('/entrees')->assertForbidden();
        $this->actingAs($admin)->get('/entrees/create')->assertForbidden();

        $this->actingAs($this->magasinier)->get('/entrees')->assertOk()
            ->assertSee('ENTRÉES / RÉCEPTIONS')->assertSee('card-header card-header-brand', false)->assertSee('entrees-filter', false);
        $this->get('/entrees/create')->assertOk()->assertSee('ARTICLES REÇUS')->assertSee('Enregistrer et valider')->assertSee('line-template', false);
    }

    public function test_a_draft_has_no_effect_on_the_stock_and_no_number(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload())->assertRedirect('/entrees');

        $entree = Entree::firstOrFail();
        $this->assertSame(DocumentStatus::Brouillon, $entree->status);
        $this->assertNull($entree->number);
        $this->assertCount(2, $entree->details);
        $this->assertSame(0.0, $this->stock($this->reglette));
        $this->assertSame(0, MouvementStock::count());
        $this->assertSame(1, ActivityLog::where('action', 'entrees.draft_saved')->count());
    }

    public function test_validation_adds_only_the_accepted_quantity_with_a_traced_movement_rg02_rg06(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'))->assertRedirect('/entrees')->assertSessionHas('print');

        $entree = Entree::firstOrFail();
        $this->assertSame(DocumentStatus::Validee, $entree->status);
        $this->assertSame('ENT-2026-0001', $entree->number);
        $this->assertSame($this->magasinier->id, $entree->validated_by);

        $this->assertSame(80.0, $this->stock($this->reglette));
        $this->assertSame(25.0, $this->stock($this->ampoule), 'Seule la quantité acceptée entre en stock (cahier §12).');

        $mouvement = MouvementStock::where('article_id', $this->ampoule->id)->firstOrFail();
        $this->assertSame(MouvementType::Entree, $mouvement->type);
        $this->assertSame('ENT-2026-0001', $mouvement->reference);
        $this->assertSame('2026-10-02', $mouvement->moved_on->toDateString());
        $this->assertSame($this->magasinier->id, $mouvement->user_id);
        $this->assertSame('25.000', $mouvement->balance_after);

        $log = ActivityLog::where('action', 'entrees.validated')->firstOrFail();
        $this->assertTrue($log->new_values['ecarts']);
        $this->assertSame([], app(StockMovementService::class)->inconsistencies(), 'Stock = somme des mouvements (RG09).');
    }

    public function test_a_draft_is_validated_from_the_list_and_numbers_follow_each_other(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload());
        $this->post('/entrees', $this->payload('draft', ['delivery_note_number' => 'BL-0955']));
        [$first, $second] = Entree::orderBy('id')->get();

        $this->post("/entrees/{$second->id}/validate")->assertRedirect('/entrees');
        $this->post("/entrees/{$first->id}/validate")->assertRedirect('/entrees');

        $this->assertSame('ENT-2026-0001', $second->fresh()->number, 'Numéro attribué à la validation (D-M5).');
        $this->assertSame('ENT-2026-0002', $first->fresh()->number);
        $this->assertSame(160.0, $this->stock($this->reglette));

        // Une entrée déjà validée ne se revalide pas.
        $this->post("/entrees/{$first->id}/validate")->assertSessionHas('error');
        $this->assertSame(160.0, $this->stock($this->reglette));
    }

    public function test_every_discrepancy_must_be_documented_rg08(): void
    {
        $this->actingAs($this->magasinier)->from('/entrees/create');

        // Manquant + rejet sans observation.
        $this->post('/entrees', $this->payload('draft', ['lines' => [1 => ['observation' => '']]]))->assertSessionHasErrors('lines.1.observation');
        // Rejet sans motif.
        $this->post('/entrees', $this->payload('draft', ['lines' => [1 => ['rejection_reason' => '']]]))->assertSessionHasErrors('lines.1.rejection_reason');
        // Acceptée > livrée.
        $this->post('/entrees', $this->payload('draft', ['lines' => [0 => ['accepted_qty' => '81']]]))->assertSessionHasErrors('lines.0.accepted_qty');
        // Excédent sans observation.
        $this->post('/entrees', $this->payload('draft', ['lines' => [0 => ['delivered_qty' => '85', 'accepted_qty' => '85']]]))->assertSessionHasErrors('lines.0.observation');

        $this->assertSame(0, Entree::count());
    }

    public function test_an_unplanned_article_is_flagged_and_must_be_explained(): void
    {
        $cable = Article::factory()->create(['designation' => 'Câble 2,5 mm²']);
        $lines = $this->payload()['lines'];
        $lines[] = ['article_id' => $cable->id, 'delivered_qty' => '50', 'accepted_qty' => '50'];

        $this->actingAs($this->magasinier)->from('/entrees/create')
            ->post('/entrees', ['lines' => $lines] + $this->payload())->assertSessionHasErrors('lines.2.observation');

        $lines[2]['observation'] = 'Ajouté par le fournisseur, accepté par le chef de service';
        $this->post('/entrees', ['lines' => $lines] + $this->payload())->assertRedirect('/entrees');

        $entree = Entree::with('details')->firstOrFail();
        $this->assertContains('Article non prévu', $entree->details[2]->discrepancies($entree->isPlanned()));
    }

    public function test_quantities_respect_the_unit_and_accept_a_decimal_comma(): void
    {
        $metre = Unite::factory()->create(['symbol' => 'm', 'allows_decimal' => true]);
        $cable = Article::factory()->create(['unite_id' => $metre->id]);

        $this->actingAs($this->magasinier)->from('/entrees/create')
            ->post('/entrees', $this->payload('draft', ['lines' => [0 => ['delivered_qty' => '2,5', 'accepted_qty' => '2,5', 'expected_qty' => '2,5']]]))
            ->assertSessionHasErrors('lines.0.delivered_qty'); // la réglette se compte à l'unité

        $this->post('/entrees', [
            'action' => 'validate', 'received_on' => '2026-10-02', 'fournisseur_id' => $this->fournisseur->id,
            'lines' => [['article_id' => $cable->id, 'delivered_qty' => '12,5', 'accepted_qty' => '12,5']],
        ])->assertRedirect('/entrees');
        $this->assertSame(12.5, $this->stock($cable));
    }

    public function test_inactive_articles_cannot_enter_rg07(): void
    {
        $this->reglette->update(['is_active' => false]);

        $this->actingAs($this->magasinier)->from('/entrees/create')->post('/entrees', $this->payload())->assertSessionHasErrors('lines.0.article_id');
    }

    public function test_an_article_deactivated_after_the_draft_blocks_the_validation(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload());
        $this->ampoule->update(['is_active' => false]);

        $this->post('/entrees/'.Entree::first()->id.'/validate')->assertSessionHas('error');
        $this->assertSame(DocumentStatus::Brouillon, Entree::first()->status);
        $this->assertSame(0, MouvementStock::count());
    }

    public function test_a_draft_is_edited_and_deleted_but_a_validated_entree_is_neither(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload());
        $draft = Entree::firstOrFail();

        $this->get("/entrees/{$draft->id}/edit")->assertOk()->assertSee('ELEC-0002 — Ampoule 30W');
        $this->put("/entrees/{$draft->id}", $this->payload('draft', ['delivery_note_number' => 'BL-9999']))->assertRedirect('/entrees');
        $this->assertSame('BL-9999', $draft->fresh()->delivery_note_number);
        $this->assertCount(2, $draft->fresh()->details, 'Les lignes sont remplacées, pas dupliquées.');

        $this->post("/entrees/{$draft->id}/validate");
        $this->get("/entrees/{$draft->id}/edit")->assertRedirect('/entrees')->assertSessionHas('error');
        $this->from('/entrees')->put("/entrees/{$draft->id}", $this->payload())->assertSessionHasErrors('entree');
        $this->delete("/entrees/{$draft->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($draft);

        $this->post('/entrees', $this->payload());
        $other = Entree::latest('id')->first();
        $this->delete("/entrees/{$other->id}")->assertRedirect('/entrees')->assertSessionHas('status');
        $this->assertSoftDeleted($other);
    }

    public function test_cancelling_reverses_the_stock_with_a_reason_rg05_rg11(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'));
        $entree = Entree::firstOrFail();

        $this->from('/entrees')->post("/entrees/{$entree->id}/cancel", ['reason' => ''])->assertSessionHasErrors('reason');

        $this->post("/entrees/{$entree->id}/cancel", ['reason' => 'Bordereau saisi en double'])->assertRedirect('/entrees')->assertSessionHas('status');

        $entree->refresh();
        $this->assertSame(DocumentStatus::Annulee, $entree->status);
        $this->assertSame('Bordereau saisi en double', $entree->cancel_reason);
        $this->assertSame(0.0, $this->stock($this->reglette));
        $this->assertSame(4, MouvementStock::count(), 'Les mouvements d\'origine restent ; l\'annulation en ajoute (RG05).');
        $this->assertSame(1, MouvementStock::where('type', MouvementType::Annulation)->where('article_id', $this->ampoule->id)->where('quantity', -25)->count());
        $this->assertSame(1, ActivityLog::where('action', 'entrees.cancelled')->count());
        $this->assertSame([], app(StockMovementService::class)->inconsistencies());
    }

    public function test_cancelling_is_refused_when_the_received_stock_is_already_gone(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'));
        $entree = Entree::firstOrFail();

        // Simule une sortie de 50 réglettes (le module Sorties arrive en phase 5).
        DB::transaction(fn () => app(StockMovementService::class)->record($this->reglette->id, -50, MouvementType::Sortie, $entree, 'SOR-TEST', $this->magasinier, '2026-10-03'));

        $this->post("/entrees/{$entree->id}/cancel", ['reason' => 'Erreur de saisie'])->assertSessionHas('error');
        $this->assertSame(DocumentStatus::Validee, $entree->fresh()->status);
        $this->assertSame(30.0, $this->stock($this->reglette));
    }

    public function test_validation_requires_its_own_permission(): void
    {
        $this->magasinier->roles->first()->revokePermissionTo('entrees.validate');
        $this->magasinier = $this->magasinier->fresh();

        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'))->assertForbidden();
        $this->post('/entrees', $this->payload())->assertRedirect('/entrees');
        $this->post('/entrees/'.Entree::first()->id.'/validate')->assertForbidden();
    }

    public function test_the_reception_sheet_is_a_pdf(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'));

        $response = $this->get('/entrees/'.Entree::first()->id.'/fiche.pdf')->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_list_shows_the_entree_with_its_discrepancy_and_the_fournisseur_history(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'));

        $this->get('/entrees')->assertOk()->assertSee('ENT-2026-0001')->assertSee('avec-ecart')->assertSee('ficheEntree-', false)->assertSee('2 manquantes, 3 cassées');
        $this->get('/fournisseurs')->assertOk()->assertSee('Historique des livraisons (1)')->assertSee('ENT-2026-0001');
        $this->get('/articles')->assertOk()->assertSee('Sous le seuil', false);
    }

    public function test_history_protects_articles_and_fournisseurs_from_deletion(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'));
        $this->magasinier->givePermissionTo(['articles.delete', 'fournisseurs.delete']);
        $this->actingAs($this->magasinier->fresh());

        $this->delete("/articles/{$this->reglette->id}")->assertSessionHas('error');
        $this->delete("/fournisseurs/{$this->fournisseur->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($this->reglette);
        $this->assertNotSoftDeleted($this->fournisseur);
    }

    public function test_movements_are_immutable(): void
    {
        $this->actingAs($this->magasinier)->post('/entrees', $this->payload('validate'));
        $mouvement = MouvementStock::firstOrFail();

        try {
            $mouvement->update(['quantity' => 1]);
            $this->fail('update interdit');
        } catch (LogicException) {
            $this->assertSame('80.000', $mouvement->fresh()->quantity);
        }

        // (L'obligation d'une transaction pour la numérotation n'est pas testable ici : RefreshDatabase enveloppe
        // déjà chaque test dans une transaction.)
        $this->expectException(LogicException::class);
        $mouvement->delete();
    }

    public function test_numbering_restarts_each_year(): void
    {
        $numbers = DB::transaction(fn () => [
            app(DocumentNumberService::class)->next('ENT', 2026),
            app(DocumentNumberService::class)->next('ENT', 2026),
            app(DocumentNumberService::class)->next('ENT', 2027),
            app(DocumentNumberService::class)->next('SOR', 2026),
        ]);

        $this->assertSame(['ENT-2026-0001', 'ENT-2026-0002', 'ENT-2027-0001', 'SOR-2026-0001'], $numbers);
    }
}
