<?php

namespace App\Policies;

use App\Models\Fournisseur;
use App\Models\User;

/**
 * Chaque action reflète la permission du manifeste config/modules/fournisseurs.php.
 * Les règles métier (ex. suppression interdite si l'objet est utilisé) vivent dans le contrôleur : `Gate::before`
 * contourne la policy pour le superadmin, pas la logique métier.
 */
class FournisseurPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fournisseurs.view');
    }

    public function view(User $user, Fournisseur $model): bool
    {
        return $user->can('fournisseurs.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fournisseurs.create');
    }

    public function update(User $user, Fournisseur $model): bool
    {
        return $user->can('fournisseurs.update');
    }

    public function delete(User $user, Fournisseur $model): bool
    {
        return $user->can('fournisseurs.delete');
    }
}
