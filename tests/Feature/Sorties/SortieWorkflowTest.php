<?php

namespace Tests\Feature\Sorties;

use App\Enums\DocumentStatus;
use App\Enums\MouvementType;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\MouvementStock;
use App\Models\Service;
use App\Models\Sortie;
use App\Models\Unite;
use App\Models\User;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\Concerns\BuildsStock;
use Tests\TestCase;

/**
 * Sorties (phase 5, cahier §13, §14) : stock visible, sortie au-delà du stock refusée (RG04), validation qui diminue
 * le stock (RG03), bon de sortie PDF, annulation par contre-mouvement. Critères de recette 15 à 18 et 20.
 */
class SortieWorkflowTest extends TestCase
{
    use BuildsFoundation, BuildsStock, RefreshDatabase;

    private User $magasinier;

    private Service $service;

    private Article $reglette;

    private Article $ampoule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create();
        $this->service = Service::factory()->create(['name' => 'Service technique']);
        $unite = Unite::factory()->create(['symbol' => 'U']);
        $this->reglette = Article::factory()->create(['reference' => 'ELEC-0001', 'designation' => 'Réglette 1,20 m LED', 'unite_id' => $unite->id]);
        $this->ampoule = Article::factory()->create(['reference' => 'ELEC-0002', 'designation' => 'Ampoule 30W', 'unite_id' => $unite->id]);
        // Tableau du cahier §13.1 : 80 réglettes, 25 ampoules en stock.
        $this->receive($this->magasinier, [$this->reglette->id => 80, $this->ampoule->id => 25]);
    }

    private function payload(string $action = 'draft', ?array $lines = null): array
    {
        return [
            'action' => $action, 'issued_on' => '2026-10-03', 'requester_name' => 'M. Keïta', 'service_id' => $this->service->id,
            'reason' => 'Remplacement des réglettes de l\'amphi A', 'paper_reference' => 'DEM-12',
            'lines' => $lines ?? [
                ['article_id' => $this->reglette->id, 'quantity' => '40'],
                ['article_id' => $this->ampoule->id, 'quantity' => '10'],
            ],
        ];
    }

    public function test_the_screens_follow_the_theme_and_show_the_stock_while_typing(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())->get('/sorties')->assertForbidden();

        $this->actingAs($this->magasinier)->get('/sorties')->assertOk()->assertSee('SORTIES DE MATÉRIEL')->assertSee('card-header card-header-brand', false);
        $this->get('/sorties/create')->assertOk()->assertSee('STOCK DISPONIBLE')->assertSee('STOCK APRÈS')->assertSee('Valider la sortie');
        // Le stock est fourni par la recherche d'articles (critère de recette 16).
        $this->getJson('/articles/search?q=ELEC-0001')->assertJsonPath('results.0.stock', '80');
    }

    public function test_a_validated_sortie_decreases_the_stock_and_freezes_the_available_stock_rg03(): void
    {
        $this->actingAs($this->magasinier)->post('/sorties', $this->payload('validate'))->assertRedirect('/sorties')->assertSessionHas('print');

        $sortie = Sortie::with('details')->firstOrFail();
        $this->assertSame('SOR-2026-0001', $sortie->number);
        $this->assertSame(DocumentStatus::Validee, $sortie->status);
        $this->assertSame(40.0, $this->stockOf($this->reglette));
        $this->assertSame(15.0, $this->stockOf($this->ampoule));
        $this->assertSame('80.000', $sortie->details[0]->stock_before);
        $this->assertSame(1, MouvementStock::where('type', MouvementType::Sortie)->where('reference', 'SOR-2026-0001')->where('quantity', -40)->count());
        $this->assertSame(1, ActivityLog::where('action', 'sorties.validated')->count());
    }

    public function test_a_sortie_above_the_stock_is_refused_rg04(): void
    {
        $this->actingAs($this->magasinier)->from('/sorties/create')
            ->post('/sorties', $this->payload('validate', [['article_id' => $this->ampoule->id, 'quantity' => '26']]))
            ->assertSessionHasErrors('lines.0.quantity');

        $this->assertStringContainsString('disponible 25, demandé 26', session('errors')->first('lines.0.quantity'));
        $this->assertSame(0, Sortie::count());
        $this->assertSame(25.0, $this->stockOf($this->ampoule));
    }

    public function test_the_stock_is_checked_again_at_validation_time_under_lock(): void
    {
        // Brouillon possible (le matériel peut arriver), mais la validation refuse si le stock a baissé entre-temps.
        $this->actingAs($this->magasinier)->post('/sorties', $this->payload('draft', [['article_id' => $this->ampoule->id, 'quantity' => '20']]));
        $draft = Sortie::firstOrFail();

        $this->post('/sorties', $this->payload('validate', [['article_id' => $this->ampoule->id, 'quantity' => '10']]));
        $this->assertSame(15.0, $this->stockOf($this->ampoule));

        $this->post("/sorties/{$draft->id}/validate")->assertSessionHas('error');
        $this->assertStringContainsString('Stock insuffisant', session('error'));
        $this->assertSame(DocumentStatus::Brouillon, $draft->fresh()->status);
        $this->assertSame(15.0, $this->stockOf($this->ampoule));
        $this->assertSame(1, Sortie::whereNotNull('number')->count(), 'Le numéro de la validation refusée n\'est pas consommé.');
        $this->assertSame([], app(StockMovementService::class)->inconsistencies());
    }

    public function test_required_fields_quantities_and_inactive_articles(): void
    {
        $this->actingAs($this->magasinier)->from('/sorties/create');

        $this->post('/sorties', ['action' => 'draft', 'lines' => []])->assertSessionHasErrors(['issued_on', 'requester_name', 'service_id', 'reason', 'lines']);
        $this->post('/sorties', $this->payload('draft', [['article_id' => $this->reglette->id, 'quantity' => '0']]))->assertSessionHasErrors('lines.0.quantity');
        $this->post('/sorties', $this->payload('draft', [['article_id' => $this->reglette->id, 'quantity' => '1,5']]))->assertSessionHasErrors('lines.0.quantity');

        $this->reglette->update(['is_active' => false]);
        $this->post('/sorties', $this->payload())->assertSessionHasErrors('lines.0.article_id');
    }

    public function test_cancelling_puts_the_material_back_with_a_reason(): void
    {
        $this->actingAs($this->magasinier)->post('/sorties', $this->payload('validate'));
        $sortie = Sortie::firstOrFail();

        $this->from('/sorties')->post("/sorties/{$sortie->id}/cancel", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/sorties/{$sortie->id}/cancel", ['reason' => 'Matériel non retiré par le service'])->assertRedirect('/sorties');

        $this->assertSame(DocumentStatus::Annulee, $sortie->fresh()->status);
        $this->assertSame(80.0, $this->stockOf($this->reglette));
        $this->assertSame(25.0, $this->stockOf($this->ampoule));
        $this->assertSame(1, ActivityLog::where('action', 'sorties.cancelled')->count());
    }

    public function test_drafts_are_editable_and_validated_sorties_are_not(): void
    {
        $this->actingAs($this->magasinier)->post('/sorties', $this->payload());
        $sortie = Sortie::firstOrFail();

        $this->get("/sorties/{$sortie->id}/edit")->assertOk()->assertSee('ELEC-0001 — Réglette 1,20 m LED');
        $this->put("/sorties/{$sortie->id}", $this->payload('validate'))->assertRedirect('/sorties');
        $this->assertSame(40.0, $this->stockOf($this->reglette));

        $this->get("/sorties/{$sortie->id}/edit")->assertRedirect('/sorties');
        $this->delete("/sorties/{$sortie->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($sortie);
    }

    public function test_the_bon_de_sortie_is_a_pdf_and_the_list_shows_the_sortie(): void
    {
        $this->actingAs($this->magasinier)->post('/sorties', $this->payload('validate'));
        $sortie = Sortie::firstOrFail();

        $response = $this->get("/sorties/{$sortie->id}/bon.pdf")->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->get('/sorties')->assertOk()->assertSee('SOR-2026-0001')->assertSee('Service technique')->assertSee('ficheSortie-'.$sortie->id, false);
    }

    public function test_validation_requires_its_own_permission(): void
    {
        $this->magasinier->roles->first()->revokePermissionTo('sorties.validate');
        $this->actingAs($this->magasinier->fresh())->post('/sorties', $this->payload('validate'))->assertForbidden();
    }
}
