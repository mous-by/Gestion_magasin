<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Bouton « œil » sur chaque saisie de mot de passe : le script commun (partials/password-toggle) est chargé par les
 * deux mises en page, donc par toute page qui contient un champ de mot de passe.
 */
class PasswordToggleTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_every_page_with_a_password_field_loads_the_eye_toggle(): void
    {
        // Connexion : son propre bouton, le script commun le respecte (pas de doublon).
        $this->get('/login')->assertOk()->assertSee('id="togglePassword"', false)->assertSee('.pw-toggle', false);

        // Changement obligatoire à la première connexion (mise en page invité).
        $this->actingAs(User::factory()->magasinier()->mustChangePassword()->create())->get('/password/change')
            ->assertOk()->assertSee('type="password"', false)->assertSee('Afficher le mot de passe');

        // Profil (mise en page de l'application).
        $this->actingAs(User::factory()->magasinier()->create())->get('/profile')
            ->assertOk()->assertSee('type="password"', false)->assertSee('Afficher le mot de passe');
    }

    public function test_no_password_field_opts_out_of_the_toggle(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getFilename() === 'password-toggle.blade.php') {
                continue; // le script lui-même documente l'option
            }
            $file = $file->getPathname();
            $this->assertStringNotContainsString('data-no-toggle', file_get_contents($file), "$file : tout champ de mot de passe doit avoir son œil.");
        }
    }
}
