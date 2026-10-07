<?php

namespace App\Policies;

use App\Models\Entree;
use App\Models\User;

/**
 * Permissions du manifeste config/modules/entrees.php. L'état du document (brouillon, validée, annulée) est une
 * règle métier appliquée par EntreeService, jamais contournée (`Gate::before` ne passe que la policy).
 */
class EntreePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('entrees.view');
    }

    public function view(User $user, Entree $entree): bool
    {
        return $user->can('entrees.view');
    }

    public function create(User $user): bool
    {
        return $user->can('entrees.create');
    }

    public function update(User $user, Entree $entree): bool
    {
        return $user->can('entrees.update');
    }

    public function delete(User $user, Entree $entree): bool
    {
        return $user->can('entrees.update');
    }

    public function validate(User $user, Entree $entree): bool
    {
        return $user->can('entrees.validate');
    }

    public function resolve(User $user, Entree $entree): bool
    {
        return $user->can('entrees.resolve');
    }

    public function cancel(User $user, Entree $entree): bool
    {
        return $user->can('entrees.cancel');
    }
}
