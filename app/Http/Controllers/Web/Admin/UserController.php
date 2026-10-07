<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleName;
use App\Exceptions\LastActiveSuperadminException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreUserRequest;
use App\Http\Requests\Web\UpdateUserRequest;
use App\Models\User;
use App\Services\Users\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Écran Utilisateurs : liste (User::visibleTo), création par UserProvisioningService::create (mot de passe
 * temporaire affiché une seule fois), modification, activer/désactiver, réinitialiser le mot de passe, révoquer
 * l'accès, suppression (soft).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        return view('users.index', [
            'users' => User::with('roles')->visibleTo($actor)->orderBy('name')->get(),
            'assignableRoles' => $this->assignableRoles($actor),
        ]);
    }

    public function store(StoreUserRequest $request, UserProvisioningService $provisioning): RedirectResponse
    {
        $role = RoleName::tryFrom((string) $request->input('role'));

        if ($role === null || ! in_array($role, $this->assignableRoles($request->user()), true)) {
            throw ValidationException::withMessages(['role' => 'Rôle invalide.']);
        }

        $result = $provisioning->create(
            $request->user(),
            $request->only(['name', 'phone']),
            $role
        );

        return redirect()->route('users.index')
            ->with('status', "Utilisateur « {$result['user']->name} » créé.")
            ->with('temporary_password', $result['temporary_password'])
            ->with('temporary_password_for', $result['user']->name);
    }

    public function update(UpdateUserRequest $request, User $user, UserProvisioningService $provisioning): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->only(['name', 'phone']);

        $wasActive = $user->is_active;
        if ($request->has('is_active') && Gate::allows('activate', $user)) {
            $data['is_active'] = $request->boolean('is_active');
        }

        try {
            $provisioning->updateByAdmin($actor, $user, $data);
        } catch (LastActiveSuperadminException $e) {
            return redirect()->route('users.index')->with('error', $e->getMessage());
        }

        if ($wasActive && ! $user->fresh()->is_active) {
            $provisioning->revokeAccess($user);
        }

        return redirect()->route('users.index')->with('status', "Utilisateur « {$user->name} » modifié.");
    }

    public function destroy(User $user, UserProvisioningService $provisioning): RedirectResponse
    {
        Gate::authorize('delete', $user);

        try {
            $user->delete();
        } catch (LastActiveSuperadminException $e) {
            return redirect()->route('users.index')->with('error', $e->getMessage());
        }

        $provisioning->revokeAccess($user);

        return redirect()->route('users.index')->with('status', "Utilisateur « {$user->name} » supprimé.");
    }

    public function resetPassword(User $user, UserProvisioningService $provisioning): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $temporary = $provisioning->resetPassword(request()->user(), $user);

        return redirect()->route('users.index')
            ->with('status', "Mot de passe réinitialisé pour {$user->name}.")
            ->with('temporary_password', $temporary)
            ->with('temporary_password_for', $user->name);
    }

    public function revokeAccess(User $user, UserProvisioningService $provisioning): RedirectResponse
    {
        Gate::authorize('revokeAccess', $user);

        $provisioning->revokeAccess($user);

        return redirect()->route('users.index')->with('status', "Accès révoqué pour {$user->name}.");
    }

    /**
     * Rôles proposables à la création (plafond de rôle), en écartant le superadmin (bootstrap seulement, D27)
     * même quand le plafond de l'acteur l'inclurait techniquement.
     *
     * @return list<RoleName>
     */
    private function assignableRoles(User $actor): array
    {
        return array_values(array_filter(
            $actor->roleName()?->manages() ?? [],
            fn (RoleName $role) => $role !== RoleName::Superadmin
        ));
    }
}
