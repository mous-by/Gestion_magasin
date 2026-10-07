<?php

namespace App\Http\Controllers\Web\Referentiels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Referentiels\ArticleRequest;
use App\Models\Article;
use App\Models\Category;
use App\Models\EntreeDetail;
use App\Models\Unite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Catalogue des articles (cahier §9, phase 3) : liste filtrable, fiche, création et modification en modale,
 * suppression en soft delete. Recherche JSON pour les champs Select2 des entrées et sorties (articles actifs, RG07).
 */
class ArticleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Article::class);

        return view('articles.index', [
            'articles' => Article::with(['category', 'unite'])->orderBy('designation')->get(),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'unites' => Unite::active()->orderBy('name')->get(['id', 'name', 'symbol', 'allows_decimal']),
        ]);
    }

    /**
     * Recherche par référence ou désignation (cahier §17), articles actifs seulement (RG07).
     * Format attendu par Select2 : `{"results": [{"id", "text"}, ...]}`.
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Article::class);

        $term = trim((string) $request->query('q', ''));

        $articles = Article::with('unite')->active()
            ->when($term !== '', fn ($query) => $query->search($term))
            ->orderBy('designation')
            ->limit(20)
            ->get()
            ->map(fn (Article $article) => [
                'id' => $article->id, 'text' => $article->label(), 'unite' => $article->unite?->symbol,
                'decimal' => (bool) $article->unite?->allows_decimal, 'stock' => $article->stockForDisplay(),
            ]);

        return response()->json(['results' => $articles]);
    }

    public function store(ArticleRequest $request): RedirectResponse
    {
        $article = Article::create($request->validated());

        return redirect()->route('articles.index')->with('status', "Article « {$article->label()} » créé.");
    }

    public function update(ArticleRequest $request, Article $article): RedirectResponse
    {
        $article->update($request->validated());

        return redirect()->route('articles.index')->with('status', "Article « {$article->label()} » modifié.");
    }

    public function destroy(Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);

        // Logique métier, jamais contournée : un article qui a un historique se désactive (RG05, RG07).
        if ($article->mouvements()->exists() || EntreeDetail::where('article_id', $article->id)->exists()) {
            return redirect()->route('articles.index')->with('error', 'Cet article a un historique (entrées, mouvements) : désactivez-le plutôt que de le supprimer.');
        }

        $article->delete();

        return redirect()->route('articles.index')->with('status', "Article « {$article->label()} » supprimé.");
    }
}
