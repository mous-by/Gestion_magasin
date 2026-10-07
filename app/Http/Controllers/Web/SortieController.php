<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SortieRequest;
use App\Models\Service;
use App\Models\Sortie;
use App\Services\Sorties\SortieService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Écran Sorties (phase 5, cahier §13, §14) : liste filtrée par période, page de saisie rapide (stock visible sur
 * chaque ligne), validation (RG03, RG04), annulation, bon de sortie PDF. Toute la logique est dans SortieService.
 */
class SortieController extends Controller
{
    public function __construct(private readonly SortieService $sorties) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Sortie::class);

        $filters = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ], ['au.after_or_equal' => 'La date de fin doit suivre la date de début.']);

        $sorties = Sortie::with(['service', 'details.article.unite', 'creator', 'validator', 'canceller'])
            ->when($filters['du'] ?? null, fn ($q, $date) => $q->whereDate('issued_on', '>=', $date))
            ->when($filters['au'] ?? null, fn ($q, $date) => $q->whereDate('issued_on', '<=', $date))
            ->orderByDesc('issued_on')->orderByDesc('id')
            ->get();

        return view('sorties.index', ['sorties' => $sorties, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', Sortie::class);

        return view('sorties.form', ['sortie' => null, 'services' => $this->services()]);
    }

    public function store(SortieRequest $request): RedirectResponse
    {
        $sortie = $this->sorties->saveDraft($request->user(), $request->validated());

        return $this->afterSave($request, $sortie);
    }

    public function edit(Sortie $sortie): View|RedirectResponse
    {
        Gate::authorize('update', $sortie);

        if (! $sortie->isDraft()) {
            return redirect()->route('sorties.index')->with('error', 'Une sortie validée ou annulée ne se modifie plus.');
        }

        return view('sorties.form', ['sortie' => $sortie->load('details.article.unite'), 'services' => $this->services($sortie)]);
    }

    public function update(SortieRequest $request, Sortie $sortie): RedirectResponse
    {
        $sortie = $this->sorties->saveDraft($request->user(), $request->validated(), $sortie);

        return $this->afterSave($request, $sortie);
    }

    public function validateSortie(Request $request, Sortie $sortie): RedirectResponse
    {
        Gate::authorize('validate', $sortie);

        try {
            $sortie = $this->sorties->validate($request->user(), $sortie);
        } catch (ValidationException $e) {
            return redirect()->route('sorties.index')->with('error', 'Validation impossible. '.collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('sorties.index')->with('status', "Sortie {$sortie->number} validée : le stock est à jour.")->with('print', $sortie->id);
    }

    public function cancel(Request $request, Sortie $sortie): RedirectResponse
    {
        Gate::authorize('cancel', $sortie);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [
            'reason.required' => 'Le motif de l\'annulation est obligatoire.',
            'reason.min' => 'Précisez le motif de l\'annulation (5 caractères au moins).',
        ]);

        try {
            $this->sorties->cancel($request->user(), $sortie, trim($data['reason']));
        } catch (ValidationException $e) {
            return redirect()->route('sorties.index')->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('sorties.index')->with('status', "Sortie {$sortie->number} annulée : le matériel est revenu en stock.");
    }

    public function destroy(Sortie $sortie): RedirectResponse
    {
        Gate::authorize('delete', $sortie);

        try {
            $this->sorties->deleteDraft($sortie);
        } catch (ValidationException $e) {
            return redirect()->route('sorties.index')->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('sorties.index')->with('status', 'Brouillon supprimé.');
    }

    /** Bon de sortie (cahier §14). Un brouillon ou une sortie annulée est marqué comme tel. */
    public function pdf(Sortie $sortie): Response
    {
        Gate::authorize('view', $sortie);

        $sortie->load(['service', 'details.article.unite', 'creator', 'validator', 'canceller']);
        $name = 'bon-de-sortie-'.($sortie->number ?? 'brouillon-'.$sortie->id).'.pdf';

        return Pdf::loadView('pdf.bon-sortie', ['sortie' => $sortie])->setPaper('a4')->stream($name);
    }

    private function afterSave(SortieRequest $request, Sortie $sortie): RedirectResponse
    {
        if ($request->input('action') !== 'validate') {
            return redirect()->route('sorties.index')->with('status', "{$sortie->displayNumber()} enregistré : il n'a pas encore d'effet sur le stock.");
        }

        try {
            $sortie = $this->sorties->validate($request->user(), $sortie);
        } catch (ValidationException $e) {
            // Le brouillon est enregistré ; la validation a échoué (stock changé entre-temps) : retour au formulaire.
            return redirect()->route('sorties.edit', $sortie)->withErrors($e->errors());
        }

        return redirect()->route('sorties.index')->with('status', "Sortie {$sortie->number} validée : le stock est à jour.")->with('print', $sortie->id);
    }

    /**
     * @return Collection<int, Service>
     */
    private function services(?Sortie $sortie = null): Collection
    {
        return Service::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($sortie, fn ($q) => $q->orWhere('id', $sortie->service_id)))
            ->orderBy('name')->get(['id', 'name', 'is_active']);
    }
}
