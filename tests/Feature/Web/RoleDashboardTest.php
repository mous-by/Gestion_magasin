<?php

namespace Tests\Feature\Web;

use App\Models\Article;
use App\Models\Entree;
use App\Models\Fournisseur;
use App\Models\Unite;
use App\Models\User;
use App\Services\Entrees\EntreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Tableau de bord par rôle (cahier §8) : chaque rôle ne voit que ce qui le concerne — magasinier : le magasin ;
 * administrateur technique : comptes et exploitation, sans les données du magasin ; superadmin : tout, plus la
 * supervision. Même règle pour la cloche de notifications.
 */
class RoleDashboardTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $magasinier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create(['name' => 'Awa Magasin']);

        // Une réception avec écart (manquant) et un article en rupture.
        $unite = Unite::factory()->create(['symbol' => 'U']);
        $ampoule = Article::factory()->create(['designation' => 'Ampoule 30W', 'unite_id' => $unite->id]);
        Article::factory()->create(['designation' => 'Boîte ronde', 'unite_id' => $unite->id]);
        $service = app(EntreeService::class);
        $service->validate($this->magasinier, $service->saveDraft($this->magasinier, [
            'received_on' => now()->toDateString(), 'fournisseur_id' => Fournisseur::factory()->create()->id,
            'lines' => [['article_id' => $ampoule->id, 'expected_qty' => 30, 'delivered_qty' => 28, 'accepted_qty' => 28, 'observation' => '2 manquantes']],
        ]));
    }

    public function test_the_magasinier_sees_his_magasin_and_nothing_of_the_administration(): void
    {
        $this->actingAs($this->magasinier)->get('/')->assertOk()
            ->assertSee('Mon magasin aujourd')
            ->assertSee('À traiter maintenant')->assertSee('Écarts à traiter')->assertSee('Articles en rupture')
            ->assertSee('ACTIVITÉ DES 14 DERNIERS JOURS')->assertSee('ARTICLES À RÉAPPROVISIONNER')->assertSee('Boîte ronde')
            ->assertSee('Nouvelle entrée')->assertSee('Nouvelle sortie')
            ->assertDontSee('Comptes et accès')->assertDontSee('Dernière sauvegarde')->assertDontSee('ACTIVITÉ PAR AGENT')
            ->assertDontSee('DERNIÈRES ACTIONS');
    }

    public function test_the_administrateur_sees_accounts_and_operations_but_no_magasin_data(): void
    {
        config(['brand.logo_candidates' => []]); // installation sans logo par défaut : la carte doit le signaler
        $this->actingAs(User::factory()->administrateur()->create())->get('/')->assertOk()
            ->assertSee('Administration technique')
            ->assertSee('Comptes et accès')->assertSee('DERNIÈRES CONNEXIONS')
            ->assertSee('Exploitation')->assertSee('Dernière sauvegarde')->assertSee('Cohérence du stock')->assertSee('Logo manquant')
            ->assertDontSee('Articles en rupture')->assertDontSee('ACTIVITÉ DES 14 DERNIERS JOURS')->assertDontSee('Ampoule 30W')
            ->assertDontSee('ACTIVITÉ PAR AGENT');
    }

    public function test_the_superadmin_has_the_widest_view(): void
    {
        $this->actingAs($this->superadmin())->get('/')->assertOk()
            ->assertSee('Supervision générale')
            ->assertSee('Magasin')->assertSee('Articles en rupture')->assertSee('ACTIVITÉ DES 14 DERNIERS JOURS')
            ->assertSee('Comptes et accès')->assertSee('Exploitation')
            ->assertSee('Supervision')->assertSee('ACTIVITÉ PAR AGENT (30 JOURS)')->assertSee('Awa Magasin')->assertSee('DERNIÈRES ACTIONS');
    }

    public function test_notifications_follow_the_same_rule(): void
    {
        $this->actingAs($this->magasinier)->get('/')->assertSee('écart(s) de réception à traiter')->assertSee('article(s) en rupture de stock');
        $this->actingAs(User::factory()->administrateur()->create())->get('/')->assertDontSee('écart(s) de réception à traiter')->assertDontSee('en rupture de stock');
    }

    public function test_a_user_without_any_module_gets_a_clear_message(): void
    {
        $bare = User::factory()->magasinier()->create();
        $bare->roles->first()->syncPermissions([]);

        $this->actingAs($bare->fresh())->get('/')->assertOk()->assertSee('Aucun module ne vous est encore attribué');
    }

    public function test_the_magasinier_never_sees_the_superadmin_as_author(): void
    {
        $superadmin = $this->superadmin(['name' => 'Compte Caché']);
        $service = app(EntreeService::class);
        $entree = $service->validate($superadmin, $service->saveDraft($superadmin, [
            'received_on' => now()->toDateString(), 'fournisseur_id' => Fournisseur::factory()->create()->id,
            'lines' => [['article_id' => Article::first()->id, 'delivered_qty' => 1, 'accepted_qty' => 1]],
        ]));

        $this->actingAs($this->magasinier)->get('/entrees')->assertOk()->assertDontSee('Compte Caché')->assertSee('Validée par');
        $this->get('/stock/'.Article::first()->id)->assertDontSee('Compte Caché')->assertSee('Administration');
        $this->get('/')->assertDontSee('Compte Caché')->assertDontSee('superadmin');
        $this->actingAs($superadmin)->get('/entrees')->assertSee('Compte Caché');
        $this->assertNotNull($entree->number);
    }

    public function test_a_discrepancy_is_resolved_and_leaves_the_alerts(): void
    {
        $entree = Entree::firstOrFail();
        $this->assertTrue($entree->hasPendingDiscrepancy());

        $this->actingAs($this->magasinier)->get('/entrees')->assertSee('Écart à traiter')->assertSee('resolveEntree-'.$entree->id, false);
        $this->from('/entrees')->post("/entrees/{$entree->id}/resolve", ['resolution' => ''])->assertSessionHasErrors('resolution');
        $this->post("/entrees/{$entree->id}/resolve", ['resolution' => 'Complément de 2 ampoules livré le lendemain (nouvelle entrée).'])->assertRedirect('/entrees');

        $entree->refresh();
        $this->assertFalse($entree->hasPendingDiscrepancy());
        $this->assertSame($this->magasinier->id, $entree->discrepancy_resolved_by);
        $this->assertSame(0, Entree::pendingDiscrepancy()->count());
        $this->get('/')->assertSee('tous traités');

        // Une seconde fois : refusé.
        $this->post("/entrees/{$entree->id}/resolve", ['resolution' => 'Encore'])->assertSessionHas('error');
    }
}
