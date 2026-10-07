<?php

namespace Tests\Feature\Referentiels;

use App\Models\ActivityLog;
use App\Models\Fournisseur;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Fournisseurs (cahier §10, critère de recette 10) et services bénéficiaires (cahier §13, §17).
 */
class FournisseurServiceScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $magasinier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create();
    }

    public function test_the_fournisseurs_screen_follows_the_theme_and_requires_the_view_permission(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())->get('/fournisseurs')->assertForbidden();

        $fournisseur = Fournisseur::factory()->create(['name' => 'Sahel Équipements']);
        $this->actingAs($this->magasinier)->get('/fournisseurs')->assertOk()
            ->assertSee('FOURNISSEURS')->assertSee('card-header card-header-brand', false)
            ->assertSee('ficheFournisseur-'.$fournisseur->id, false)->assertSee('Sahel Équipements');
    }

    public function test_a_fournisseur_is_created_with_the_fields_of_the_cahier_and_audited(): void
    {
        $this->actingAs($this->magasinier)->post('/fournisseurs', [
            'name' => 'Ets Diarra & Frères', 'address' => 'Ségou, quartier Médine', 'phone' => '+223 21 32 00 00',
            'email' => 'contact@diarra.example', 'contact_person' => 'M. Diarra', 'admin_references' => 'NIF 123',
        ])->assertRedirect('/fournisseurs');

        $fournisseur = Fournisseur::where('name', 'Ets Diarra & Frères')->firstOrFail();
        $this->assertSame('M. Diarra', $fournisseur->contact_person);
        $this->assertSame(1, ActivityLog::where('action', 'fournisseurs.created')->where('subject_id', $fournisseur->id)->count());
    }

    public function test_fournisseur_validation(): void
    {
        Fournisseur::factory()->create(['name' => 'Doublon']);

        $this->actingAs($this->magasinier)->from('/fournisseurs')
            ->post('/fournisseurs', ['name' => 'Doublon', 'email' => 'pas-un-email', 'phone' => 'appeler le matin'])
            ->assertSessionHasErrors(['name', 'email', 'phone']);
    }

    public function test_a_fournisseur_is_deactivated_by_update_and_deleted_only_with_the_optional_permission(): void
    {
        $fournisseur = Fournisseur::factory()->create();

        $this->actingAs($this->magasinier)->put("/fournisseurs/{$fournisseur->id}", ['name' => $fournisseur->name])->assertRedirect('/fournisseurs');
        $this->assertFalse($fournisseur->fresh()->is_active);

        $this->delete("/fournisseurs/{$fournisseur->id}")->assertForbidden();
        $this->magasinier->givePermissionTo('fournisseurs.delete');
        $this->actingAs($this->magasinier->fresh())->delete("/fournisseurs/{$fournisseur->id}")->assertRedirect('/fournisseurs');
        $this->assertSoftDeleted($fournisseur);
    }

    public function test_services_are_managed_from_the_referentiels_tabs(): void
    {
        $this->actingAs($this->magasinier)->get('/services')->assertOk()->assertSee('SERVICES BÉNÉFICIAIRES')->assertSee('Articles');

        $this->post('/services', ['name' => 'Faculté des Sciences', 'code' => 'FS'])->assertRedirect('/services');
        $this->from('/services')->post('/services', ['name' => 'Autre', 'code' => 'FS'])->assertSessionHasErrors('code');
        $this->assertSame(1, Service::count());
    }
}
