<?php

namespace App\Http\Controllers\Web\Referentiels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Referentiels\UniteRequest;
use App\Models\Unite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Écran Unités (phase 3) : liste, création et modification en modale, suppression en soft delete.
 */
class UniteController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Unite::class);

        return view('unites.index', ['unites' => Unite::query()->withCount('articles')->orderBy('name')->get()]);
    }

    public function store(UniteRequest $request): RedirectResponse
    {
        $unite = Unite::create($request->validated());

        return redirect()->route('unites.index')->with('status', "Unité « {$unite->name} » créée.");
    }

    public function update(UniteRequest $request, Unite $unite): RedirectResponse
    {
        $unite->update($request->validated());

        return redirect()->route('unites.index')->with('status', "Unité « {$unite->name} » modifiée.");
    }

    public function destroy(Unite $unite): RedirectResponse
    {
        Gate::authorize('delete', $unite);

        // Logique métier, jamais contournée (même par le superadmin) : un référentiel utilisé se désactive.
        if ($unite->articles()->withTrashed()->exists()) {
            return redirect()->route('unites.index')->with('error', 'Cette unité est utilisée par des articles : désactivez-la plutôt que de la supprimer.');
        }

        $unite->delete();

        return redirect()->route('unites.index')->with('status', "Unité « {$unite->name} » supprimée.");
    }
}
