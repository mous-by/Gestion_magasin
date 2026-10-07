<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Permissions\PermissionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Exceptions de permissions par utilisateur : on ne propose jamais tout le catalogue, mais le « pool »
 * du rôle de la personne CIBLÉE (optional du manifeste + permissions custom), jamais une permission réservée.
 */
class UserPermissionController extends Controller
{
    public function index(Request $request, PermissionManager $manager): View
    {
        $users = User::with('roles')
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'superadmin'))
            ->orderBy('name')
            ->get();

        $selected = $request->filled('user') ? $users->firstWhere('id', (int) $request->input('user')) : null;

        return view('user-permissions.index', [
            'users' => $users,
            'selected' => $selected,
            'pool' => $selected ? $manager->poolFor($selected) : null,
            'direct' => $selected ? $selected->permissions()->pluck('name')->all() : [],
        ]);
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('user-permissions.index', ['user' => $user->id]);
    }

    public function update(Request $request, User $user, PermissionManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $manager->syncUserPermissions($request->user(), $user, $validated['permissions'] ?? []);

        return redirect()->route('user-permissions.index', ['user' => $user->id])->with('status', "Permissions mises à jour pour {$user->name}.");
    }
}
