<?php

namespace App\Http\Controllers\Web\Referentiels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Referentiels\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Écran Catégories (phase 3) : liste, création et modification en modale, suppression en soft delete.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        return view('categories.index', ['categories' => Category::query()->withCount('articles')->orderBy('name')->get()]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('categories.index')->with('status', "Catégorie « {$category->name} » créée.");
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('status', "Catégorie « {$category->name} » modifiée.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        // Logique métier, jamais contournée (même par le superadmin) : un référentiel utilisé se désactive.
        if ($category->articles()->withTrashed()->exists()) {
            return redirect()->route('categories.index')->with('error', 'Cette catégorie est utilisée par des articles : désactivez-la plutôt que de la supprimer.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', "Catégorie « {$category->name} » supprimée.");
    }
}
