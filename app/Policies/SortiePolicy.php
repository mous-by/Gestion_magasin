<?php

namespace App\Policies;

use App\Models\Sortie;
use App\Models\User;

/**
 * Permissions du manifeste config/modules/sorties.php. L'état du document est une règle métier de SortieService.
 */
class SortiePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sorties.view');
    }

    public function view(User $user, Sortie $sortie): bool
    {
        return $user->can('sorties.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sorties.create');
    }

    public function update(User $user, Sortie $sortie): bool
    {
        return $user->can('sorties.update');
    }

    public function delete(User $user, Sortie $sortie): bool
    {
        return $user->can('sorties.update');
    }

    public function validate(User $user, Sortie $sortie): bool
    {
        return $user->can('sorties.validate');
    }

    public function cancel(User $user, Sortie $sortie): bool
    {
        return $user->can('sorties.cancel');
    }
}
