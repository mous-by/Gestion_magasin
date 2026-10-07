<?php

namespace Tests\Feature\Referentiels;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Category;
use App\Models\Unite;
use App\Models\User;
use Database\Seeders\UniteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Catégories et unités (cahier §9, §24) : permission `articles.manage_referentiels`, noms uniques, et un
 * référentiel utilisé par un article ne se supprime pas (il se désactive).
 */
class CategoryUniteScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $magasinier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create();
    }

    public function test_both_screens_follow_the_theme_and_require_the_permission(): void
    {
        $admin = User::factory()->administrateur()->create();

        foreach (['/categories' => 'CATÉGORIES D', '/unites' => 'UNITÉS DE MESURE'] as $uri => $heading) {
            $this->actingAs($admin)->get($uri)->assertForbidden();
            $this->actingAs($this->magasinier)->get($uri)->assertOk()->assertSee($heading)->assertSee('card-header card-header-brand', false);
        }
    }

    public function test_a_category_is_created_updated_and_its_name_is_unique(): void
    {
        $this->actingAs($this->magasinier)->post('/categories', ['name' => '  Électricité ', 'description' => ''])->assertRedirect('/categories');
        $category = Category::where('name', 'Électricité')->firstOrFail();
        $this->assertNull($category->description);

        $this->from('/categories')->post('/categories', ['name' => 'Électricité'])->assertSessionHasErrors('name');

        $this->put("/categories/{$category->id}", ['name' => 'Électricité', 'description' => 'Ampoules, câbles', 'category_id' => $category->id])->assertRedirect('/categories');
        $this->assertFalse($category->fresh()->is_active, 'Case « Actif » non cochée.');
        $this->assertSame(1, ActivityLog::where('action', 'categories.updated')->count());
    }

    public function test_a_used_category_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $used = Category::factory()->create();
        Article::factory()->create(['category_id' => $used->id]);
        $unused = Category::factory()->create();

        $this->actingAs($this->magasinier)->delete("/categories/{$used->id}")->assertRedirect('/categories')->assertSessionHas('error');
        $this->assertNotSoftDeleted($used);

        $this->delete("/categories/{$unused->id}")->assertRedirect('/categories')->assertSessionHas('status');
        $this->assertSoftDeleted($unused);
    }

    public function test_a_unit_has_a_unique_name_and_symbol_and_the_decimal_option(): void
    {
        $this->actingAs($this->magasinier)->post('/unites', ['name' => 'Mètre', 'symbol' => 'm', 'allows_decimal' => '1'])->assertRedirect('/unites');
        $this->assertTrue(Unite::where('symbol', 'm')->firstOrFail()->allows_decimal);

        $this->from('/unites')->post('/unites', ['name' => 'Mètre linéaire', 'symbol' => 'm'])->assertSessionHasErrors('symbol');
        $this->from('/unites')->post('/unites', ['name' => 'Mètre', 'symbol' => 'ml'])->assertSessionHasErrors('name');
    }

    public function test_a_used_unit_cannot_be_deleted(): void
    {
        $unite = Unite::factory()->create();
        Article::factory()->create(['unite_id' => $unite->id])->delete(); // même un article supprimé la référence encore

        $this->actingAs($this->magasinier)->delete("/unites/{$unite->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($unite);
    }

    public function test_the_initial_units_of_the_cahier_are_seeded_idempotently(): void
    {
        $this->seed(UniteSeeder::class);
        $this->seed(UniteSeeder::class);

        $this->assertSame(['U', 'm', 'pqt', 'rlx'], Unite::orderBy('symbol')->pluck('symbol')->sort()->values()->all());
    }
}
