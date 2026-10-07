<?php

namespace App\Policies;

use App\Models\Unite;
use App\Models\User;

/**
 * Unités : toutes les actions relèvent de `articles.manage_referentiels`.
 * Les règles métier (ex. suppression interdite si l'objet est utilisé) vivent dans le contrôleur : `Gate::before`
 * contourne la policy pour le superadmin, pas la logique métier.
 */
class UnitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('articles.manage_referentiels');
    }

    public function view(User $user, Unite $model): bool
    {
        return $user->can('articles.manage_referentiels');
    }

    public function create(User $user): bool
    {
        return $user->can('articles.manage_referentiels');
    }

    public function update(User $user, Unite $model): bool
    {
        return $user->can('articles.manage_referentiels');
    }

    public function delete(User $user, Unite $model): bool
    {
        return $user->can('articles.manage_referentiels');
    }
}
