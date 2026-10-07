<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Catalogue des rôles, en lecture seule (roles.view, réservée au superadmin). Seules les permissions
 * personnalisées d'un rôle s'ajustent d'ici (RolePermissionController) ; les défauts viennent des manifestes.
 */
class RoleController extends Controller
{
    public function index(ModuleRegistry $registry): View
    {
        $roles = Role::where('guard_name', 'web')->with('permissions')->get()->keyBy('name');

        $rows = collect(RoleName::cases())->map(fn (RoleName $name) => [
            'name' => $name,
            'role' => $roles->get($name->value),
            'users' => User::role($name->value)->count(),
            'defaults' => $registry->roleDefaults($name),
            'optional' => $registry->roleOptional($name),
            'custom' => $roles->get($name->value)?->permissions->where('source', Permission::SOURCE_CUSTOM)->pluck('name')->sort()->values()->all() ?? [],
            'manages' => array_map(fn (RoleName $r) => $r->label(), $name->manages()),
        ]);

        return view('roles.index', [
            'rows' => $rows,
            'customPermissions' => Permission::custom()->orderBy('name')->pluck('name'),
        ]);
    }
}
