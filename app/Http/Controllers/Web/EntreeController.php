<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\EntreeRequest;
use App\Models\Entree;
use App\Models\Fournisseur;
use App\Services\Entrees\EntreeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Écran Entrées (phase 4, cahier §11, §12) : liste filtrée par période, page de saisie dédiée (lignes multiples,
 * CLAUDE.md §7 règle 3), validation, annulation, fiche de réception PDF. Toute la logique est dans EntreeService.
 */
class EntreeController extends Controller
{
    public function __construct(private readonly EntreeService $entrees) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Entree::class);

        $filters = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ], ['au.after_or_equal' => 'La date de fin doit suivre la date de début.']);

        $entrees = Entree::with(['fournisseur', 'details.article.unite', 'creator', 'validator', 'canceller', 'resolver'])
            ->when($filters['du'] ?? null, fn ($q, $date) => $q->whereDate('received_on', '>=', $date))
            ->when($filters['au'] ?? null, fn ($q, $date) => $q->whereDate('received_on', '<=', $date))
            ->orderByDesc('received_on')->orderByDesc('id')
            ->get();

        return view('entrees.index', ['entrees' => $entrees, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', Entree::class);

        return view('entrees.form', ['entree' => null, 'fournisseurs' => $this->fournisseurs()]);
    }

    public function store(EntreeRequest $request): RedirectResponse
    {
        $entree = $this->entrees->saveDraft($request->user(), $request->entreeData());

        return $this->afterSave($request, $entree);
    }

    public function edit(Entree $entree): View|RedirectResponse
    {
        Gate::authorize('update', $entree);

        if (! $entree->isDraft()) {
            return redirect()->route('entrees.index')->with('error', 'Une entrée validée ou annulée ne se modifie plus.');
        }

        return view('entrees.form', ['entree' => $entree->load('details.article.unite'), 'fournisseurs' => $this->fournisseurs($entree)]);
    }

    public function update(EntreeRequest $request, Entree $entree): RedirectResponse
    {
        $entree = $this->entrees->saveDraft($request->user(), $request->entreeData(), $entree);

        return $this->afterSave($request, $entree);
    }

    public function validateEntree(Request $request, Entree $entree): RedirectResponse
    {
        Gate::authorize('validate', $entree);

        try {
            $entree = $this->entrees->validate($request->user(), $entree);
        } catch (ValidationException $e) {
            return redirect()->route('entrees.index')->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('entrees.index')->with('status', "Entrée {$entree->number} validée : le stock est à jour.")->with('print', $entree->id);
    }

    public function cancel(Request $request, Entree $entree): RedirectResponse
    {
        Gate::authorize('cancel', $entree);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [
            'reason.required' => 'Le motif de l\'annulation est obligatoire.',
            'reason.min' => 'Précisez le motif de l\'annulation (5 caractères au moins).',
        ]);

        try {
            $this->entrees->cancel($request->user(), $entree, trim($data['reason']));
        } catch (ValidationException $e) {
            return redirect()->route('entrees.index')->with('error', 'Annulation impossible. '.collect($e->errors())->flatten()->implode(' ').' Une partie de la réception est sans doute déjà sortie : passez par une régularisation.');
        }

        return redirect()->route('entrees.index')->with('status', "Entrée {$entree->number} annulée : le stock a été corrigé.");
    }

    public function resolve(Request $request, Entree $entree): RedirectResponse
    {
        Gate::authorize('resolve', $entree);

        $data = $request->validate(['resolution' => ['required', 'string', 'min:5', 'max:1000']], [
            'resolution.required' => 'Indiquez la suite donnée à l\'écart.',
            'resolution.min' => 'Précisez la suite donnée (5 caractères au moins).',
        ]);

        try {
            $this->entrees->resolveDiscrepancy($request->user(), $entree, trim($data['resolution']));
        } catch (ValidationException $e) {
            return redirect()->route('entrees.index')->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('entrees.index')->with('status', "Écart de l'entrée {$entree->number} traité.");
    }

    public function destroy(Entree $entree): RedirectResponse
    {
        Gate::authorize('delete', $entree);

        try {
            $this->entrees->deleteDraft($entree);
        } catch (ValidationException $e) {
            return redirect()->route('entrees.index')->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('entrees.index')->with('status', 'Brouillon supprimé.');
    }

    /** Fiche d'entrée / réception (cahier §18). Un brouillon ou une entrée annulée est marqué comme tel. */
    public function pdf(Entree $entree): Response
    {
        Gate::authorize('view', $entree);

        $entree->load(['fournisseur', 'details.article.unite', 'creator', 'validator', 'canceller', 'resolver']);
        $name = 'fiche-reception-'.($entree->number ?? 'brouillon-'.$entree->id).'.pdf';

        return Pdf::loadView('pdf.fiche-entree', ['entree' => $entree])->setPaper('a4')->stream($name);
    }

    private function afterSave(EntreeRequest $request, Entree $entree): RedirectResponse
    {
        if ($request->input('action') !== 'validate') {
            return redirect()->route('entrees.index')->with('status', "{$entree->displayNumber()} enregistré : il n'a pas encore d'effet sur le stock.");
        }

        try {
            $entree = $this->entrees->validate($request->user(), $entree);
        } catch (ValidationException $e) {
            // Le brouillon est enregistré ; la validation a échoué : retour au formulaire avec le motif.
            return redirect()->route('entrees.edit', $entree)->withErrors($e->errors());
        }

        return redirect()->route('entrees.index')->with('status', "Entrée {$entree->number} validée : le stock est à jour.")->with('print', $entree->id);
    }

    /**
     * @return Collection<int, Fournisseur>
     */
    private function fournisseurs(?Entree $entree = null)
    {
        return Fournisseur::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($entree, fn ($q) => $q->orWhere('id', $entree->fournisseur_id)))
            ->orderBy('name')->get(['id', 'name', 'is_active']);
    }
}
