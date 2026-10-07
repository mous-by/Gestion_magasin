<?php

namespace App\Http\Controllers\Web\Referentiels;

use App\Http\Controllers\Controller;
use App\Services\Articles\ArticleImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import Excel des articles (ArticleImportService) : modèle à télécharger, envoi du fichier rempli, rapport.
 */
class ArticleImportController extends Controller
{
    public function __construct(private readonly ArticleImportService $imports) {}

    /** Modèle Excel à remplir, avec les catégories et unités actuelles en listes déroulantes. */
    public function template(): StreamedResponse
    {
        $book = $this->imports->template();

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'modele-import-articles.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,ods,csv,txt'],
        ], [
            'fichier.required' => 'Choisissez le fichier à importer.',
            'fichier.mimes' => 'Format accepté : .xlsx, .xls, .ods ou .csv.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ]);

        $file = $request->file('fichier');
        $report = $this->imports->import($request->user(), $file->getRealPath(), $file->getClientOriginalName(), $request->boolean('mettre_a_jour'));

        if (! $report['ok']) {
            return redirect()->route('articles.index')
                ->with('error', count($report['errors']).' erreur(s) : aucun article n\'a été importé. Corrigez le fichier et recommencez.')
                ->with('import_errors', array_slice($report['errors'], 0, 200));
        }

        $message = "Import terminé : {$report['created']} article(s) créé(s), {$report['updated']} mis à jour, {$report['skipped']} ignoré(s)";
        $message .= $report['categories'] !== [] ? ', catégorie(s) créée(s) : '.implode(', ', $report['categories']).'.' : '.';

        return redirect()->route('articles.index')->with('status', $message);
    }
}
