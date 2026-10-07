<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * Lecture des manifestes config/modules/*.php (D6, D7) : permissions, défauts par rôle, menu.
 * Un module s'ajoute en déposant un manifeste, sans modifier ce fichier.
 */
class ModuleRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function modules(): array
    {
        return config('modules', []);
    }

    /**
     * Toutes les permissions déclarées par les manifestes.
     *
     * @return Collection<string, array{name: string, module: string, action: string, label: string, reserved: bool}>
     */
    public function permissions(): Collection
    {
        $all = collect();

        foreach ($this->modules() as $module => $manifest) {
            foreach ($manifest['permissions'] ?? [] as $action => $label) {
                $name = "{$module}.{$action}";
                $all[$name] = [
                    'name' => $name,
                    'module' => $module,
                    'action' => $action,
                    'label' => $label,
                    'reserved' => $this->isReserved($name),
                ];
            }
        }

        return $all;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->permissions()->keys()->all();
    }

    public function has(string $name): bool
    {
        return $this->permissions()->has($name);
    }

    /**
     * Réservée : jamais attribuable à un autre que le superadmin. Vrai si le manifeste la déclare réservée
     * ou si son module est un module technique réservé.
     */
    public function isReserved(string $name): bool
    {
        [$module, $action] = array_pad(explode('.', $name, 2), 2, '');

        return in_array($module, config('authorization.reserved_modules', []), true)
            || in_array($action, $this->modules()[$module]['reserved'] ?? [], true);
    }

    /**
     * Permissions accordées par défaut à un rôle.
     *
     * @return list<string>
     */
    public function roleDefaults(RoleName|string $role): array
    {
        return $this->roleList($role, 'defaults');
    }

    /**
     * Permissions attribuables en exception à un utilisateur de ce rôle.
     *
     * @return list<string>
     */
    public function roleOptional(RoleName|string $role): array
    {
        return $this->roleList($role, 'optional');
    }

    /**
     * Refuse un manifeste incohérent : action inconnue, ou permission réservée offerte à un rôle autre que le superadmin.
     */
    public function assertValid(): void
    {
        foreach ($this->modules() as $module => $manifest) {
            foreach ($manifest['reserved'] ?? [] as $action) {
                if (! isset($manifest['permissions'][$action])) {
                    throw new InvalidArgumentException("Manifeste « {$module} » : l'action réservée « {$action} » n'est pas déclarée.");
                }
            }

            foreach ($manifest['roles'] ?? [] as $role => $sets) {
                if (RoleName::tryFrom($role) === null) {
                    throw new InvalidArgumentException("Manifeste « {$module} » : rôle inconnu « {$role} ».");
                }

                foreach (['defaults', 'optional'] as $kind) {
                    foreach ($sets[$kind] ?? [] as $action) {
                        $name = "{$module}.{$action}";
                        if (! isset($manifest['permissions'][$action])) {
                            throw new InvalidArgumentException("Manifeste « {$module} » : « {$name} » n'est pas déclarée.");
                        }
                        if ($this->isReserved($name)) {
                            throw new InvalidArgumentException("Manifeste « {$module} » : « {$name} » est réservée au superadmin, elle ne peut pas être offerte au rôle « {$role} ».");
                        }
                    }
                }
            }
        }
    }

    /**
     * Entrées de menu visibles par l'utilisateur : permission accordée et route existante.
     *
     * `position => 'bottom'` place l'entrée sous tous les autres menus (Paramètres) ; `active` accepte un ou plusieurs motifs de route.
     *
     * @return list<array{label: string, icon: string, route: string, active: string|list<string>, order: int, position?: string}>
     */
    public function navigation(User $user): array
    {
        $entries = [];

        foreach ($this->modules() as $manifest) {
            foreach ($manifest['navigation'] ?? [] as $entry) {
                if (! Route::has($entry['route']) || ! $user->can($entry['permission'])) {
                    continue;
                }
                $entries[] = $entry + ['order' => 50];
            }
        }

        usort($entries, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $entries;
    }

    /**
     * Écrans qui s'ouvrent depuis le sous-menu Paramètres plutôt que depuis une entrée directe de la sidebar
     * (`navigation => []` dans leur manifeste) : source unique, réutilisée par `configuration/_menu.blade.php`
     * (la liste elle-même) et par la sidebar (pour savoir si l'entrée « Paramètres » doit apparaître, et vers
     * quel écran la faire pointer — le premier accessible, pas toujours Rôles qui reste réservé au superadmin).
     *
     * @return list<array{route: string, permission: string, label: string, icon: string, active: string}>
     */
    public function settingsItems(): array
    {
        return [
            ['route' => 'users.index', 'permission' => 'users.view', 'label' => 'Utilisateurs', 'icon' => 'bx bx-user-circle', 'active' => 'users.index'],
            ['route' => 'documents.settings', 'permission' => 'documents.manage', 'label' => 'Documents', 'icon' => 'bx bx-file', 'active' => 'documents.settings*'],
            ['route' => 'roles.index', 'permission' => 'roles.view', 'label' => 'Rôles', 'icon' => 'bx bx-id-card', 'active' => 'roles.*'],
            ['route' => 'permissions.index', 'permission' => 'permissions.view', 'label' => 'Permissions', 'icon' => 'bx bx-shield-alt-2', 'active' => 'permissions.*'],
            ['route' => 'user-permissions.index', 'permission' => 'permissions.assign', 'label' => 'Attribution des permissions', 'icon' => 'bx bx-user-check', 'active' => 'user-permissions.*'],
            ['route' => 'audit.index', 'permission' => 'audit.view', 'label' => 'Audit', 'icon' => 'bx bx-list-check', 'active' => 'audit.*'],
            ['route' => 'system.index', 'permission' => 'system.view', 'label' => 'Système', 'icon' => 'bx bx-server', 'active' => 'system.*'],
        ];
    }

    /**
     * Premier élément de settingsItems() que $user peut atteindre (route existante et permission accordée), ou
     * null si aucun — dans ce cas l'entrée « Paramètres » ne doit pas apparaître du tout (fail-closed).
     *
     * @return array{route: string, permission: string, label: string, icon: string, active: string}|null
     */
    public function firstAccessibleSettingsItem(User $user): ?array
    {
        foreach ($this->settingsItems() as $item) {
            if (Route::has($item['route']) && $user->can($item['permission'])) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function roleList(RoleName|string $role, string $kind): array
    {
        $role = $role instanceof RoleName ? $role->value : $role;
        $names = [];

        foreach ($this->modules() as $module => $manifest) {
            foreach ($manifest['roles'][$role][$kind] ?? [] as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }
}
