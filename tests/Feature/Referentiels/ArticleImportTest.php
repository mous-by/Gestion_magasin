<?php

namespace Tests\Feature\Referentiels;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Category;
use App\Models\Unite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Import Excel des articles : modèle à télécharger (listes déroulantes), import tout ou rien avec rapport d'erreurs,
 * catégories créées, unités vérifiées, références existantes ignorées ou mises à jour, audit.
 */
class ArticleImportTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $magasinier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->magasinier = User::factory()->magasinier()->create();
        Category::factory()->create(['name' => 'Éclairage']);
        Unite::factory()->create(['name' => 'Unité', 'symbol' => 'U']);
        Unite::factory()->create(['name' => 'Mètre', 'symbol' => 'm', 'allows_decimal' => true]);
    }

    /** Fichier .xlsx au format du modèle (titres en ligne 1). */
    private function xlsx(array $rows, ?array $headers = null): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Articles');
        $sheet->fromArray([$headers ?? ['Référence *', 'Désignation *', 'Catégorie *', 'Unité *', 'Marque / modèle', 'Seuil minimum', 'Emplacement', 'Observation', 'Statut'], ...$rows], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return new UploadedFile($path, 'articles.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_the_template_is_an_excel_file_with_lists_from_the_application(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())->get('/articles/import/modele')->assertForbidden();

        $response = $this->actingAs($this->magasinier)->get('/articles/import/modele')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);

        $this->assertSame(['Articles', 'Listes', 'Mode d\'emploi'], $book->getSheetNames());
        $this->assertSame('Référence *', $book->getSheetByName('Articles')->getCell('A1')->getValue());
        $this->assertSame('Éclairage', $book->getSheetByName('Listes')->getCell('A2')->getValue());
        $this->assertSame('=Listes!$B$2:$B$3', $book->getSheetByName('Articles')->getCell('D2')->getDataValidation()->getFormula1());
    }

    public function test_a_valid_file_creates_articles_and_missing_categories(): void
    {
        $this->actingAs($this->magasinier)->post('/articles/import', ['fichier' => $this->xlsx([
            ['ecl-101', 'Ampoule LED 9 W', 'Éclairage', 'Unité', 'Ingelec', 20, 'Rayon A3', null, 'Actif'],
            ['CAB-101', 'Câble rigide 1,5 mm²', 'Câbles et fils', 'm', null, '12,5', null, null, 'Inactif'],
            [null, null, null, null, null, null, null, null, null], // ligne vide ignorée
        ])])->assertRedirect('/articles')->assertSessionHas('status');

        $this->assertSame('ECL-101', Article::where('designation', 'Ampoule LED 9 W')->value('reference'));
        $cable = Article::where('reference', 'CAB-101')->firstOrFail();
        $this->assertSame('12,5', $cable->thresholdForDisplay());
        $this->assertFalse($cable->is_active);
        $this->assertTrue(Category::where('name', 'Câbles et fils')->exists());
        $this->assertStringContainsString('2 article(s) créé(s)', session('status'));
        $this->assertSame(1, ActivityLog::where('action', 'articles.imported')->count());
    }

    public function test_any_error_rejects_the_whole_file_with_a_report(): void
    {
        $this->actingAs($this->magasinier)->post('/articles/import', ['fichier' => $this->xlsx([
            ['OK-001', 'Ligne correcte', 'Éclairage', 'Unité', null, null, null, null, null],
            ['OK-002', 'Unité inconnue', 'Éclairage', 'Kilo', null, null, null, null, null],
            ['OK-001', '', 'Éclairage', 'Unité', null, '2,5', null, null, 'Peut-être'],
        ])])->assertRedirect('/articles')->assertSessionHas('error');

        $this->assertSame(0, Article::count(), 'Tout ou rien : la ligne correcte n\'est pas importée non plus.');
        $messages = collect(session('import_errors'))->map(fn ($e) => $e['line'].' '.$e['column'].' '.$e['message'])->implode("\n");
        foreach (['3 Unité', '4 Référence', '4 Désignation', '4 Seuil minimum', '4 Statut'] as $expected) {
            $this->assertStringContainsString($expected, $messages);
        }
    }

    public function test_existing_references_are_skipped_or_updated_on_request(): void
    {
        $existing = Article::factory()->create(['reference' => 'ECL-101', 'designation' => 'Ancienne désignation']);

        $file = fn () => $this->xlsx([['ECL-101', 'Nouvelle désignation', 'Éclairage', 'Unité', null, null, null, null, null]]);

        $this->actingAs($this->magasinier)->post('/articles/import', ['fichier' => $file()])->assertSessionHas('status');
        $this->assertSame('Ancienne désignation', $existing->fresh()->designation);
        $this->assertStringContainsString('1 ignoré(s)', session('status'));

        $this->post('/articles/import', ['fichier' => $file(), 'mettre_a_jour' => '1'])->assertSessionHas('status');
        $this->assertSame('Nouvelle désignation', $existing->fresh()->designation);
        $this->assertSame(1, ActivityLog::where('action', 'articles.updated')->where('subject_id', $existing->id)->count());
    }

    public function test_columns_are_found_by_title_and_bad_files_are_refused(): void
    {
        // Colonnes dans un autre ordre, titres sans astérisque.
        $this->actingAs($this->magasinier)->post('/articles/import', ['fichier' => $this->xlsx(
            [['U', 'Éclairage', 'Réglette', 'ECL-200']],
            ['Unité', 'Catégorie', 'Désignation', 'Référence'],
        )])->assertSessionHas('status');
        $this->assertTrue(Article::where('reference', 'ECL-200')->exists());

        $this->post('/articles/import', ['fichier' => $this->xlsx([['x']], ['Nom', 'Prix'])])->assertSessionHas('import_errors');
        $this->from('/articles')->post('/articles/import', ['fichier' => UploadedFile::fake()->create('photo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('fichier');
        $this->actingAs(User::factory()->administrateur()->create())->post('/articles/import', ['fichier' => $this->xlsx([])])->assertForbidden();
    }
}
