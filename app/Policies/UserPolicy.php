<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Plafond de rôle (CLAUDE.md §4.2) : au-delà de la permission, un acteur n'agit que sur un compte que son rôle peut
 * gérer. L'administrateur technique voit toute la liste (sauf les superadmins) mais n'agit ni sur un autre
 * administrateur, ni sur un superadmin. Un compte sans rôle n'est gérable par personne (fail-closed).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update') && $this->canManage($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete') && $this->canManage($user, $target);
    }

    public function activate(User $user, User $target): bool
    {
        return $user->can('users.activate') && $this->canManage($user, $target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->can('users.reset_password') && $this->canManage($user, $target);
    }

    public function revokeAccess(User $user, User $target): bool
    {
        return $user->can('users.revoke_access') && $this->canManage($user, $target);
    }

    private function canManage(User $user, User $target): bool
    {
        return $user->roleName()?->canManage($target->roleName() ?? RoleName::Superadmin) ?? false;
    }
}
