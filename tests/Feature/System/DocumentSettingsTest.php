<?php

namespace Tests\Feature\System;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Documents\DocumentNumberService;
use App\Support\DocumentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Paramètres → Documents : en-tête, logo, signataires et numérotation modifiables depuis l'interface (rien de figé
 * dans le code), réservés à `documents.manage`, audités.
 */
class DocumentSettingsTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        Storage::fake('local');
        config(['brand.logo_candidates' => []]); // installation sans logo par défaut dans public/
        $this->admin = User::factory()->administrateur()->create();
    }

    private function payload(array $overrides = []): array
    {
        $settings = app(DocumentSettings::class);

        return array_replace_recursive([
            'header' => $settings->header(),
            'signatures' => collect(DocumentSettings::DOCUMENTS)->mapWithKeys(fn ($l, $doc) => [$doc => $settings->rawSignatures($doc)])->all(),
            'numbering' => ['format' => '{prefix}-{year}-{number}', 'padding' => 4, 'prefixes' => ['entree' => 'ENT', 'sortie' => 'SOR', 'regularisation' => 'REG']],
        ], $overrides);
    }

    public function test_the_screen_is_reserved_to_documents_manage_and_follows_the_settings_menu(): void
    {
        $this->actingAs(User::factory()->magasinier()->create())->get('/parametres/documents')->assertForbidden();

        $this->actingAs($this->admin)->get('/parametres/documents')->assertOk()
            ->assertSee('PARAMÈTRES')->assertSee('EN-TÊTE OFFICIEL')->assertSee('SIGNATAIRES')->assertSee('NUMÉROTATION')
            ->assertSee('Visa du Chef de Service des Finances')->assertSee('{valide_par}');
    }

    public function test_names_and_header_are_saved_audited_and_printed_with_tokens(): void
    {
        $this->actingAs($this->admin)->put('/parametres/documents', $this->payload([
            'header' => ['entity' => 'Service Patrimoine'],
            'signatures' => ['bon_sortie' => [2 => ['label' => 'Visa du Chef de Service des Finances', 'name' => 'M. Bakary TRAORÉ']]],
        ]))->assertRedirect('/parametres/documents')->assertSessionHas('status');

        $settings = app(DocumentSettings::class);
        $this->assertSame('Service Patrimoine', $settings->header()['entity']);
        $this->assertSame([
            ['label' => 'Le Demandeur', 'name' => 'M. Keïta'],
            ['label' => 'Le Comptable Matière', 'name' => 'Awa Traoré'],
            ['label' => 'Visa du Chef de Service des Finances', 'name' => 'M. Bakary TRAORÉ'],
        ], $settings->signatures('bon_sortie', ['demandeur' => 'M. Keïta', 'valide_par' => 'Awa Traoré']));

        // Seules les valeurs différentes du défaut sont stockées, et chaque écriture est auditée.
        $this->assertSame(['header.entity', 'signatures.bon_sortie'], AppSetting::orderBy('key')->pluck('key')->all());
        $this->assertSame(2, ActivityLog::where('module', 'documents')->where('action', 'documents.created')->count());

        $this->assertSame('application/pdf', $this->get('/parametres/documents/apercu')->headers->get('content-type'));
    }

    public function test_the_numbering_format_is_configurable_but_must_stay_unique(): void
    {
        $this->actingAs($this->admin)->from('/parametres/documents')
            ->put('/parametres/documents', $this->payload(['numbering' => ['format' => '{prefix}-{number}']]))
            ->assertSessionHasErrors('numbering.format');
        $this->put('/parametres/documents', $this->payload(['numbering' => ['prefixes' => ['sortie' => 'ENT']]]))
            ->assertSessionHasErrors('numbering.prefixes');

        $this->put('/parametres/documents', $this->payload(['numbering' => ['format' => '{year}-{number}/US-{prefix}', 'padding' => 3, 'prefixes' => ['sortie' => 'MAG']]]))
            ->assertRedirect('/parametres/documents');

        $settings = app(DocumentSettings::class);
        $number = DB::transaction(fn () => app(DocumentNumberService::class)->next($settings->prefix('sortie'), 2026));
        $this->assertSame('2026-001/US-MAG', $number);
    }

    public function test_the_logo_is_uploaded_shown_and_removed(): void
    {
        $this->actingAs($this->admin)->from('/parametres/documents')
            ->post('/parametres/documents/logo', ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('logo');

        $this->post('/parametres/documents/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 120)])->assertRedirect('/parametres/documents');
        $settings = app(DocumentSettings::class);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $settings->logoDataUri());
        $stored = AppSetting::firstWhere('key', 'logo.path')->value;
        Storage::disk('local')->assertExists($stored);

        $this->delete('/parametres/documents/logo')->assertRedirect('/parametres/documents');
        Storage::disk('local')->assertMissing($stored);
        $this->assertNull(app(DocumentSettings::class)->logoDataUri());
    }
}
