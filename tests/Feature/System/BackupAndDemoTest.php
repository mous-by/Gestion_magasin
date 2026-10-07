<?php

namespace Tests\Feature\System;

use App\Enums\DocumentStatus;
use App\Models\Article;
use App\Models\Entree;
use App\Models\Fournisseur;
use App\Models\Service;
use App\Models\Sortie;
use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use App\Services\Stock\StockMovementService;
use Database\Seeders\CatalogueInitialSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Livrables des phases 8 et 9 : contrôle des sauvegardes (cahier §22), jeu de démonstration (§30) et guide
 * d'utilisation en ligne (§30, §31).
 */
class BackupAndDemoTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->dir = storage_path('framework/testing/backups');
        File::deleteDirectory($this->dir);
        config(['backup.path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_backup_verify_accepts_a_complete_dump_and_rejects_a_broken_one(): void
    {
        $this->artisan('backup:verify')->assertFailed(); // aucune sauvegarde

        File::ensureDirectoryExists($this->dir);
        $tables = collect(['users', 'articles', 'entrees', 'sorties', 'mouvements_stock', 'audit_logs'])->map(fn ($t) => "CREATE TABLE `{$t}` (id int);")->implode("\n");
        file_put_contents($this->dir.'/db_20261001_020000.sql.gz', gzencode($tables."\n-- Dump completed on 2026-10-01"));
        file_put_contents($this->dir.'/db_20261002_020000.sql.gz', gzencode("CREATE TABLE `users` (id int);\n"));

        $this->assertSame($this->dir.'/db_20261002_020000.sql.gz', app(DatabaseBackup::class)->latest());
        $this->artisan('backup:verify')->assertFailed(); // la plus récente est tronquée
        $this->artisan('backup:verify', ['file' => $this->dir.'/db_20261001_020000.sql.gz'])->assertSuccessful();
    }

    public function test_the_real_catalogue_is_seeded_idempotently(): void
    {
        $this->seed(CatalogueInitialSeeder::class);
        $this->seed(CatalogueInitialSeeder::class);

        $this->assertSame(count(CatalogueInitialSeeder::ARTICLES), Article::count());
        $this->assertSame('Réglette complète 1,20 m Ingelec LED', Article::where('reference', 'ECL-002')->value('designation'));
        $this->assertTrue(Fournisseur::where('name', 'Mamadou A DIALLO — Commerce Général')->exists());
        $this->assertTrue(Service::where('code', 'US-SP')->exists());
    }

    public function test_the_demo_replays_the_real_documents_through_the_services(): void
    {
        $this->superadmin();

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class); // idempotent

        $stock = fn (string $reference) => (float) Article::where('reference', $reference)->value('stock_quantity');
        $this->assertSame(40.0, $stock('ECL-002'), '80 livrées (bordereau) − 40 sorties (bon 2026-029/US-SP).');
        $this->assertSame(0.0, $stock('CAB-003'), '50 m livrés, 50 m sortis.');
        $this->assertSame(27.0, $stock('PIL-002'), '47 livrés, 20 refusés (sales).');
        $this->assertSame(0.0, $stock('APP-017'), 'Boîte ronde : demandée mais jamais livrée.');

        $validated = Sortie::where('status', DocumentStatus::Validee->value)->firstOrFail();
        $this->assertSame('2026-029/US-SP', $validated->paper_reference);
        $this->assertCount(18, $validated->details);
        $draft = Sortie::where('status', DocumentStatus::Brouillon->value)->with('details')->firstOrFail();
        $this->assertCount(5, $draft->details, 'Les 5 articles non livrés restent en brouillon : leur validation serait refusée (RG04).');
        $this->assertSame(1, Entree::count());
        $this->assertSame([], app(StockMovementService::class)->inconsistencies());
    }

    public function test_the_user_guide_covers_the_training_topics(): void
    {
        $page = $this->actingAs(User::factory()->magasinier()->create())->get('/aide')->assertOk()->assertSee('GUIDE D\'UTILISATION', false);

        foreach (['Se connecter', 'Créer un article', 'Enregistrer une entrée', 'Contrôler une livraison', 'Enregistrer une sortie', 'Imprimer un bon', 'Rechercher un mouvement', 'Consulter le stock', 'Générer les rapports', 'Corriger une erreur'] as $topic) {
            $page->assertSee($topic);
        }
    }
}
