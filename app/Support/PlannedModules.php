<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Modules métier prévus mais non implémentés (D25) : liste affichée dans la sidebar et fiche par module.
 * Un module implémenté (manifeste config/modules/<clé>.php présent) sort automatiquement de cette liste.
 */
class PlannedModules
{
    /**
     * Modules visibles par l'utilisateur, triés : le superadmin et l'admin national voient tout, les autres rôles Web
     * voient ceux que le cahier leur attribue.
     *
     * @return array<string, array<string, mixed>>
     */
    public function visibleTo(User $user): array
    {
        $role = $user->roleName();
        $seesAll = in_array($role, [RoleName::Superadmin, RoleName::Administrateur], true);

        return collect(config('planned_modules', []))
            ->reject(fn ($module, $key) => config("modules.{$key}") !== null)
            ->filter(fn ($module) => $seesAll || ($role !== null && in_array($role->value, $module['roles'] ?? [], true)))
            ->sortBy('order')
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key, User $user): ?array
    {
        return $this->visibleTo($user)[$key] ?? null;
    }
}
