<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Permissions\PermissionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function update(Request $request, Role $role, PermissionManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $manager->syncRoleCustomPermissions($request->user(), $role, $validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('status', "Permissions personnalisées du rôle « {$role->name} » mises à jour.");
    }
}
