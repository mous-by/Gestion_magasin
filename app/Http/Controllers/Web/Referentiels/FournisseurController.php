<?php

namespace App\Http\Controllers\Web\Referentiels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Referentiels\FournisseurRequest;
use App\Models\Fournisseur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Écran Fournisseurs (phase 3) : liste, création et modification en modale, suppression en soft delete.
 */
class FournisseurController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Fournisseur::class);

        return view('fournisseurs.index', ['fournisseurs' => Fournisseur::query()
            ->with(['entrees' => fn ($q) => $q->whereNotNull('number')->with('details')->latest('received_on')])
            ->orderBy('name')->get()]);
    }

    public function store(FournisseurRequest $request): RedirectResponse
    {
        $fournisseur = Fournisseur::create($request->validated());

        return redirect()->route('fournisseurs.index')->with('status', "Fournisseur « {$fournisseur->name} » créé.");
    }

    public function update(FournisseurRequest $request, Fournisseur $fournisseur): RedirectResponse
    {
        $fournisseur->update($request->validated());

        return redirect()->route('fournisseurs.index')->with('status', "Fournisseur « {$fournisseur->name} » modifié.");
    }

    public function destroy(Fournisseur $fournisseur): RedirectResponse
    {
        Gate::authorize('delete', $fournisseur);

        // Logique métier, jamais contournée : un fournisseur qui a des livraisons se désactive.
        if ($fournisseur->entrees()->withTrashed()->exists()) {
            return redirect()->route('fournisseurs.index')->with('error', 'Ce fournisseur a des entrées enregistrées : désactivez-le plutôt que de le supprimer.');
        }

        $fournisseur->delete();

        return redirect()->route('fournisseurs.index')->with('status', "Fournisseur « {$fournisseur->name} » supprimé.");
    }
}
