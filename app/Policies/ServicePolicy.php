<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Chaque action reflète la permission du manifeste config/modules/services.php.
 * Les règles métier (ex. suppression interdite si l'objet est utilisé) vivent dans le contrôleur : `Gate::before`
 * contourne la policy pour le superadmin, pas la logique métier.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, Service $model): bool
    {
        return $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, Service $model): bool
    {
        return $user->can('services.update');
    }

    public function delete(User $user, Service $model): bool
    {
        return $user->can('services.delete');
    }
}
