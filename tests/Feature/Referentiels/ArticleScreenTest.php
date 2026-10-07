<?php

namespace Tests\Feature\Referentiels;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Category;
use App\Models\Unite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Catalogue des articles (cahier §9, phase 3) : fiche, référence unique (RG01), article inactif (RG07), recherche
 * (cahier §17), permissions et audit. Critère de recette 9 : créer et rechercher un article.
 */
class ArticleScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $magasinier;

    private Category $category;

    private Unite $unite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create();
        $this->category = Category::factory()->create(['name' => 'Électricité']);
        $this->unite = Unite::factory()->create(['name' => 'Unité', 'symbol' => 'U']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'reference' => 'elec-0001', 'designation' => 'Réglette 1,20 m LED', 'category_id' => $this->category->id,
            'unite_id' => $this->unite->id, 'min_threshold' => '20', 'location' => 'Rayon A1',
        ], $overrides);
    }

    public function test_the_screen_follows_the_theme_and_requires_the_view_permission(): void
    {
        $this->get('/articles')->assertRedirect('/login');
        $this->actingAs(User::factory()->administrateur()->create())->get('/articles')->assertForbidden();

        $this->actingAs($this->magasinier)->get('/articles')->assertOk()
            ->assertSee('CATALOGUE DES ARTICLES')
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('createArticleModal', false)
            ->assertSee('Catégories')->assertSee('Unités'); // onglets des référentiels
    }

    public function test_a_magasinier_creates_an_article_with_the_reference_in_capitals_and_it_is_audited(): void
    {
        $this->actingAs($this->magasinier)->post('/articles', $this->payload(['min_threshold' => '2,5', 'unite_id' => Unite::factory()->create(['allows_decimal' => true])->id]))
            ->assertRedirect('/articles')->assertSessionHas('status');

        $article = Article::where('reference', 'ELEC-0001')->firstOrFail();
        $this->assertSame('Réglette 1,20 m LED', $article->designation);
        $this->assertSame('2,5', $article->thresholdForDisplay());
        $this->assertTrue($article->is_active);
        $this->assertSame(1, ActivityLog::where('action', 'articles.created')->where('subject_id', $article->id)->count());
    }

    public function test_the_reference_is_unique_even_among_deleted_articles_rg01(): void
    {
        Article::factory()->create(['reference' => 'ELEC-0001'])->delete();

        $this->actingAs($this->magasinier)->from('/articles')->post('/articles', $this->payload())
            ->assertSessionHasErrors('reference');
        $this->assertSame(0, Article::count());
    }

    public function test_required_fields_and_formats_are_checked(): void
    {
        $this->actingAs($this->magasinier)->from('/articles')
            ->post('/articles', ['reference' => '', 'designation' => '', 'category_id' => '', 'unite_id' => ''])
            ->assertSessionHasErrors(['reference', 'designation', 'category_id', 'unite_id']);

        $this->post('/articles', $this->payload(['reference' => 'É#1']))->assertSessionHasErrors('reference');
        $this->post('/articles', $this->payload(['min_threshold' => '-1']))->assertSessionHasErrors('min_threshold');
        // L'unité « Unité » n'admet pas de décimale.
        $this->post('/articles', $this->payload(['min_threshold' => '2,5']))->assertSessionHasErrors('min_threshold');
    }

    public function test_an_inactive_category_or_unit_cannot_be_chosen_for_a_new_article(): void
    {
        $inactive = Category::factory()->inactive()->create();

        $this->actingAs($this->magasinier)->from('/articles')->post('/articles', $this->payload(['category_id' => $inactive->id]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_an_article_keeps_its_category_even_after_the_category_is_deactivated(): void
    {
        $article = Article::factory()->create(['category_id' => $this->category->id, 'unite_id' => $this->unite->id]);
        $this->category->update(['is_active' => false]);

        $this->actingAs($this->magasinier)->put("/articles/{$article->id}", $this->payload(['reference' => $article->reference, 'designation' => 'Nouvelle désignation', 'is_active' => '1']))
            ->assertRedirect('/articles')->assertSessionHasNoErrors();

        $this->assertSame('Nouvelle désignation', $article->fresh()->designation);
    }

    public function test_updating_deactivates_an_article_and_logs_old_and_new_values(): void
    {
        $article = Article::factory()->create(['reference' => 'ELEC-0001', 'category_id' => $this->category->id, 'unite_id' => $this->unite->id]);

        $this->actingAs($this->magasinier)->put("/articles/{$article->id}", $this->payload(['reference' => 'ELEC-0001']))
            ->assertRedirect('/articles');

        $this->assertFalse($article->fresh()->is_active, 'Case non cochée : article désactivé.');
        $log = ActivityLog::where('action', 'articles.updated')->latest('id')->firstOrFail();
        // Valeurs brutes de la base (1 / 0), comme pour tout champ booléen audité.
        $this->assertTrue((bool) $log->old_values['is_active']);
        $this->assertFalse((bool) $log->new_values['is_active']);
    }

    public function test_the_search_finds_active_articles_by_reference_or_designation_rg07(): void
    {
        Article::factory()->create(['reference' => 'ELEC-0002', 'designation' => 'Ampoule 30W', 'unite_id' => $this->unite->id]);
        Article::factory()->create(['reference' => 'ELEC-0003', 'designation' => 'Ampoule 60W'])->update(['is_active' => false]);
        Article::factory()->create(['reference' => 'BUR-0001', 'designation' => 'Rame de papier A4']);

        $this->actingAs($this->magasinier);
        $this->getJson('/articles/search?q=ampoule')->assertOk()->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', 'ELEC-0002 — Ampoule 30W')->assertJsonPath('results.0.unite', 'U');
        $this->getJson('/articles/search?q=BUR-')->assertJsonCount(1, 'results');
    }

    public function test_deletion_is_an_optional_permission_and_a_soft_delete(): void
    {
        $article = Article::factory()->create();

        $this->actingAs($this->magasinier)->delete("/articles/{$article->id}")->assertForbidden();

        $this->magasinier->givePermissionTo('articles.delete');
        $this->actingAs($this->magasinier->fresh())->delete("/articles/{$article->id}")->assertRedirect('/articles');
        $this->assertSoftDeleted($article);
    }

    public function test_the_list_shows_filters_and_a_sheet_per_article(): void
    {
        $article = Article::factory()->create(['designation' => 'Câble 2,5 mm²', 'category_id' => $this->category->id]);

        $this->actingAs($this->magasinier)->get('/articles')->assertOk()
            ->assertSee('articles-filter', false)
            ->assertSee('articles-category-filter', false)
            ->assertSee('ficheArticle-'.$article->id, false)
            ->assertSee('Câble 2,5 mm²');
    }
}
