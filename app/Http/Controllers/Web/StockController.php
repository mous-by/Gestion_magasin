<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\MouvementStock;
use App\Services\Stock\RegularisationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stock et mouvements (phase 6, cahier §15, §16) : état du stock avec alertes (sous le seuil, rupture), fiche de
 * mouvement d'un article (écran et PDF), régularisation justifiée (RG11). Le stock n'est jamais saisi : il résulte
 * des mouvements validés (RG09).
 */
class StockController extends Controller
{
    public function index(): View
    {
        Gate::authorize('stock.view');

        $lastMoves = MouvementStock::query()->selectRaw('article_id, MAX(moved_on) as last_moved_on')->groupBy('article_id')->pluck('last_moved_on', 'article_id');

        return view('stock.index', [
            'articles' => Article::with(['category', 'unite'])->orderBy('designation')->get(),
            'lastMoves' => $lastMoves,
        ]);
    }

    public function show(Request $request, Article $article): View
    {
        Gate::authorize('stock.view');

        return view('stock.show', $this->sheet($request, $article));
    }

    /** Fiche de mouvement d'un article (cahier §18). */
    public function pdf(Request $request, Article $article): Response
    {
        Gate::authorize('stock.view');

        return Pdf::loadView('pdf.fiche-mouvement', $this->sheet($request, $article))->setPaper('a4')
            ->stream('fiche-mouvement-'.$article->reference.'.pdf');
    }

    public function regularize(Request $request, Article $article, RegularisationService $regularisations): RedirectResponse
    {
        Gate::authorize('stock.regularize');

        $request->merge(['new_balance' => str_replace([',', ' '], ['.', ''], (string) $request->input('new_balance'))]);
        $data = $request->validate([
            'new_balance' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'regularized_on' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'new_balance.required' => 'Indiquez le stock réellement constaté.',
            'reason.required' => 'Le motif de la régularisation est obligatoire (RG11).',
            'reason.min' => 'Précisez le motif (5 caractères au moins).',
            'regularized_on.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ]);

        if (! $article->unite?->allows_decimal && floor((float) $data['new_balance']) != (float) $data['new_balance']) {
            throw ValidationException::withMessages(['new_balance' => "L'unité « {$article->unite?->name} » n'admet pas de quantité décimale."]);
        }

        $regularisation = $regularisations->regularize($request->user(), $article, round((float) $data['new_balance'], 3), trim($data['reason']), $data['regularized_on']);

        return back()->with('status', "Régularisation {$regularisation->number} enregistrée : stock de {$article->reference} corrigé.");
    }

    /**
     * Données de la fiche de mouvement : solde d'ouverture de la période, mouvements, totaux (cahier §15).
     *
     * @return array<string, mixed>
     */
    private function sheet(Request $request, Article $article): array
    {
        $filters = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ], ['au.after_or_equal' => 'La date de fin doit suivre la date de début.']);

        $base = MouvementStock::where('article_id', $article->id);

        // Solde d'ouverture : somme des mouvements antérieurs à la période (même ordre que la saisie).
        $opening = isset($filters['du']) ? (float) (clone $base)->where('moved_on', '<', $filters['du'])->sum('quantity') : 0.0;

        $mouvements = (clone $base)->with(['user.roles', 'source'])
            ->when($filters['du'] ?? null, fn ($q, $date) => $q->where('moved_on', '>=', $date))
            ->when($filters['au'] ?? null, fn ($q, $date) => $q->where('moved_on', '<=', $date))
            ->orderBy('moved_on')->orderBy('id')
            ->get();

        // Solde courant recalculé ligne à ligne dans l'ordre chronologique affiché.
        $balance = $opening;
        $rows = $mouvements->map(function (MouvementStock $mouvement) use (&$balance) {
            $balance = round($balance + (float) $mouvement->quantity, 3);

            return ['mouvement' => $mouvement, 'balance' => $balance];
        });

        return [
            'article' => $article->load(['category', 'unite']),
            'filters' => $filters,
            'opening' => $opening,
            'rows' => $rows,
            'totalIn' => $mouvements->where('quantity', '>', 0)->sum(fn ($m) => (float) $m->quantity),
            'totalOut' => $mouvements->where('quantity', '<', 0)->sum(fn ($m) => -1 * (float) $m->quantity),
            'closing' => $balance,
        ];
    }
}
