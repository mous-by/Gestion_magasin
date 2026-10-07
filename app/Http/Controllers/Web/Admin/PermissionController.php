<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Services\Permissions\PermissionManager;
use App\Support\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Référentiel des permissions : les deux voies côte à côte (D23). Les permissions des manifestes (source
 * `manifest`) sont en lecture seule ; les permissions `custom` se créent et se suppriment ici.
 */
class PermissionController extends Controller
{
    public function index(Request $request, ModuleRegistry $registry): View
    {
        $search = trim((string) $request->query('search'));

        $grouped = Permission::withCount(['roles', 'users'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => $permission->module());

        $data = [
            'grouped' => $grouped,
            'labels' => $registry->permissions(),
            'registry' => $registry,
        ];

        return $request->ajax() ? view('permissions._groups', $data) : view('permissions.index', $data + ['search' => $search]);
    }

    public function store(Request $request, PermissionManager $manager): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:150']]);

        $permission = $manager->createCustom($request->user(), $validated['name']);

        return redirect()->route('permissions.index')->with('status', "Permission « {$permission->name} » créée.");
    }

    public function destroy(Request $request, Permission $permission, PermissionManager $manager): RedirectResponse
    {
        $manager->deleteCustom($request->user(), $permission);

        return redirect()->route('permissions.index')->with('status', "Permission « {$permission->name} » supprimée.");
    }
}
