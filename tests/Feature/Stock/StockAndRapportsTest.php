<?php

namespace Tests\Feature\Stock;

use App\Enums\MouvementType;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\MouvementStock;
use App\Models\Regularisation;
use App\Models\Service;
use App\Models\Unite;
use App\Models\User;
use App\Services\Sorties\SortieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\BuildsFoundation;
use Tests\Concerns\BuildsStock;
use Tests\TestCase;

/**
 * Stock (phase 6, cahier §8, §15, §16) et rapports (phase 7, §17, §18) : état et alertes, fiche de mouvement
 * conforme au tableau du §15, régularisation justifiée (RG11), cohérence (RG09), tableau de bord, rapports PDF et
 * tableur. Critères de recette 19, 21 à 23.
 */
class StockAndRapportsTest extends TestCase
{
    use BuildsFoundation, BuildsStock, RefreshDatabase;

    private User $magasinier;

    private Article $reglette;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create();
        $this->reglette = Article::factory()->create(['reference' => 'ELEC-0001', 'designation' => 'Réglette 1,20 m LED', 'min_threshold' => 50, 'unite_id' => Unite::factory()->create(['symbol' => 'U'])->id]);
    }

    /** Tableau du cahier §15 : ENT 80 le 02/10, SOR 40 le 03/10, ENT 20 le 04/10 → solde 60. */
    private function cahierScenario(): void
    {
        $this->receive($this->magasinier, [$this->reglette->id => 80], '2026-10-02');
        $sorties = app(SortieService::class);
        $sortie = $sorties->saveDraft($this->magasinier, [
            'issued_on' => '2026-10-03', 'requester_name' => 'M. Keïta', 'service_id' => Service::factory()->create()->id, 'reason' => 'Amphi A',
            'lines' => [['article_id' => $this->reglette->id, 'quantity' => 40]],
        ]);
        $sorties->validate($this->magasinier, $sortie);
        $this->receive($this->magasinier, [$this->reglette->id => 20], '2026-10-04');
    }

    public function test_the_movement_sheet_matches_the_table_of_the_cahier(): void
    {
        $this->cahierScenario();

        $page = $this->actingAs($this->magasinier)->get("/stock/{$this->reglette->id}")->assertOk()->assertSee('FICHE DE MOUVEMENT');
        $page->assertSeeInOrder(['02/10/2026', 'ENT-2026-0001', 'Réception', '80', '03/10/2026', 'SOR-2026-0001', 'Sortie', '40', '04/10/2026', 'ENT-2026-0002', 'Réception', '20', '60']);
        $this->assertSame(60.0, $this->stockOf($this->reglette));

        // Période : solde d'ouverture au 03/10 = 80.
        $this->get("/stock/{$this->reglette->id}?du=2026-10-03")->assertSee('Solde au 03/10/2026')->assertDontSee('ENT-2026-0001');
        $this->assertSame('application/pdf', $this->get("/stock/{$this->reglette->id}/fiche.pdf")->headers->get('content-type'));
    }

    public function test_the_stock_screen_flags_low_and_empty_stock(): void
    {
        $this->cahierScenario(); // 60 > seuil 50
        Article::factory()->create(['designation' => 'Câble vide']);

        $this->actingAs($this->magasinier)->get('/stock')->assertOk()->assertSee('ÉTAT DU STOCK')
            ->assertSee('niveau-rupture')->assertSee('Câble vide')->assertSee('60 U');
        $this->actingAs(User::factory()->administrateur()->create())->get('/stock')->assertForbidden();
    }

    public function test_a_regularisation_is_numbered_justified_and_traced_rg11(): void
    {
        $this->receive($this->magasinier, [$this->reglette->id => 80]);

        $this->actingAs($this->magasinier)->from("/stock/{$this->reglette->id}")
            ->post("/stock/{$this->reglette->id}/regularisations", ['new_balance' => '77', 'reason' => '', 'regularized_on' => now()->toDateString()])
            ->assertSessionHasErrors('reason');
        $this->post("/stock/{$this->reglette->id}/regularisations", ['new_balance' => '80', 'reason' => 'Comptage', 'regularized_on' => now()->toDateString()])
            ->assertSessionHasErrors('new_balance'); // aucun écart
        $this->post("/stock/{$this->reglette->id}/regularisations", ['new_balance' => '77,5', 'reason' => 'Comptage', 'regularized_on' => now()->toDateString()])
            ->assertSessionHasErrors('new_balance'); // l'unité n'admet pas de décimale

        $this->post("/stock/{$this->reglette->id}/regularisations", ['new_balance' => '77', 'reason' => '3 réglettes cassées au rangement', 'regularized_on' => now()->toDateString()])
            ->assertSessionHas('status');

        $regularisation = Regularisation::firstOrFail();
        $this->assertSame('REG-'.now()->year.'-0001', $regularisation->number);
        $this->assertSame('80.000', $regularisation->old_balance);
        $this->assertSame('77.000', $regularisation->new_balance);
        $this->assertSame(77.0, $this->stockOf($this->reglette));
        $this->assertSame(1, MouvementStock::where('type', MouvementType::Regularisation)->where('quantity', -3)->count());
        $log = ActivityLog::where('action', 'stock.regularized')->firstOrFail();
        $this->assertSame(['stock' => 80], $log->old_values);

        $this->expectException(LogicException::class);
        $regularisation->update(['reason' => 'modifié']);
    }

    public function test_stock_verify_detects_an_inconsistent_cache_rg09(): void
    {
        $this->receive($this->magasinier, [$this->reglette->id => 80]);
        $this->artisan('stock:verify')->assertSuccessful();

        DB::table('articles')->where('id', $this->reglette->id)->update(['stock_quantity' => 99]);
        $this->artisan('stock:verify')->assertFailed();
    }

    public function test_the_dashboard_shows_the_situation_of_the_magasin(): void
    {
        $this->cahierScenario();
        Article::factory()->create();

        $this->actingAs($this->magasinier)->get('/')->assertOk()
            ->assertSee('Articles en rupture')->assertSee('Sous le seuil')->assertSee('Entrées à valider')
            ->assertSee('DERNIÈRES ENTRÉES')->assertSee('DERNIÈRES SORTIES')->assertSee('DERNIERS ÉCARTS DE LIVRAISON')
            ->assertSee('Nouvelle entrée')->assertSee('Nouvelle sortie')->assertSee('SOR-2026-0001');
    }

    public function test_reports_are_generated_in_pdf_and_csv(): void
    {
        $this->cahierScenario();
        $this->actingAs($this->magasinier)->get('/rapports')->assertOk()->assertSee('ÉTAT DE STOCK')->assertSee('RAPPORT DES ÉCARTS DE LIVRAISON');

        foreach (['etat-stock', 'entrees', 'sorties', 'ecarts'] as $type) {
            $this->assertSame('application/pdf', $this->get("/rapports/{$type}?du=2026-10-01&au=2026-10-31")->assertOk()->headers->get('content-type'), $type);
        }

        $csv = $this->get('/rapports/entrees?format=csv&du=2026-10-01')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ENT-2026-0002', $csv);
        $this->assertSame(1, ActivityLog::where('action', 'rapports.exported')->count());

        // État de stock à une date passée : recalculé depuis les mouvements (80 au 02/10).
        $csv = $this->get('/rapports/etat-stock?format=csv&au=2026-10-02')->streamedContent();
        $this->assertMatchesRegularExpression('/ELEC-0001;.*;80;Normal/u', $csv);

        $this->get('/rapports/inconnu')->assertNotFound();
        $this->actingAs(User::factory()->administrateur()->create())->get('/rapports')->assertForbidden();
    }

    public function test_csv_export_requires_its_permission(): void
    {
        $this->magasinier->roles->first()->revokePermissionTo('rapports.export');

        $this->actingAs($this->magasinier->fresh())->get('/rapports/sorties?format=csv')->assertForbidden();
        $this->get('/rapports/sorties')->assertOk();
    }
}
