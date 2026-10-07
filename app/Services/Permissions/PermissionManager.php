<?php

namespace App\Services\Permissions;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\ModuleRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Voie B (D23) : gestion des permissions depuis l'interface.
 * Toutes les opérations sont réservées au superadmin (permissions réservées), transactionnelles et auditées.
 */
class PermissionManager
{
    public function __construct(private readonly ModuleRegistry $registry, private readonly ActivityLogger $logger) {}

    /**
     * Crée une permission `custom`.
     *
     * @throws ValidationException
     */
    public function createCustom(User $actor, string $name): Permission
    {
        Gate::forUser($actor)->authorize('permissions.create');

        $name = trim($name);
        $this->assertValidName($name);

        return DB::transaction(function () use ($name) {
            return Permission::create(['name' => $name, 'guard_name' => 'web', 'source' => Permission::SOURCE_CUSTOM]);
        });
    }

    /**
     * Supprime une permission `custom` (les permissions issues des manifestes ne se suppriment pas d'ici).
     */
    public function deleteCustom(User $actor, Permission $permission): void
    {
        Gate::forUser($actor)->authorize('permissions.create');

        if (! $permission->isCustom()) {
            throw ValidationException::withMessages(['permission' => 'Une permission déclarée dans un manifeste ne peut pas être supprimée depuis l\'interface.']);
        }

        DB::transaction(fn () => $permission->delete());
    }

    /**
     * Permissions qu'on peut accorder à $target en exception (additif, D6) : les `optional` de son rôle dans les
     * manifestes, plus toutes les permissions `custom` — jamais une permission réservée, jamais tout le catalogue.
     *
     * @return array{inherited: Collection<int, Permission>, assignable: Collection<int, Permission>}
     */
    public function poolFor(User $target): array
    {
        $role = $target->roleName();

        if ($role === null || $role === RoleName::Superadmin) {
            return ['inherited' => collect(), 'assignable' => collect()];
        }

        $optional = $this->registry->roleOptional($role);
        $inherited = $this->registry->roleDefaults($role);

        $assignable = Permission::query()
            ->where(fn ($q) => $q->whereIn('name', $optional)->orWhere('source', Permission::SOURCE_CUSTOM))
            ->whereNotIn('name', $inherited)
            ->orderBy('name')
            ->get()
            ->reject(fn (Permission $p) => $this->registry->isReserved($p->name))
            ->values();

        return [
            'inherited' => Permission::whereIn('name', $inherited)->orderBy('name')->get(),
            'assignable' => $assignable,
        ];
    }

    /**
     * Remplace les exceptions directes de $target par $names (qui doivent appartenir à son pool).
     *
     * @param  list<string>  $names
     * @return array{added: list<string>, removed: list<string>}
     */
    public function syncUserPermissions(User $actor, User $target, array $names): array
    {
        Gate::forUser($actor)->authorize('permissions.assign');

        if ($target->isSuperadmin()) {
            throw ValidationException::withMessages(['permissions' => 'Le superadmin a déjà tous les accès : rien à attribuer.']);
        }

        $allowed = $this->poolFor($target)['assignable']->pluck('name')->all();
        $invalid = array_values(array_diff($names, $allowed));

        if ($invalid !== []) {
            throw ValidationException::withMessages(['permissions' => 'Permission(s) non attribuable(s) à ce rôle : '.implode(', ', $invalid).'.']);
        }

        return DB::transaction(function () use ($actor, $target, $names) {
            $before = $target->permissions()->pluck('name')->all();
            $target->syncPermissions($names);
            $after = $target->permissions()->pluck('name')->all();

            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));

            if ($added !== [] || $removed !== []) {
                $this->logger->log(
                    'permissions.user_synced',
                    'permissions',
                    "Exceptions de permissions modifiées — {$target->name}",
                    $target,
                    old: ['permissions' => $before],
                    new: ['permissions' => $after],
                    actor: $actor,
                );
            }

            return ['added' => $added, 'removed' => $removed];
        });
    }

    /**
     * Remplace les permissions `custom` d'un rôle. Les permissions issues des manifestes ne changent pas :
     * elles restent pilotées par le code et permissions:sync.
     *
     * @param  list<string>  $customNames
     * @return array{added: list<string>, removed: list<string>}
     */
    public function syncRoleCustomPermissions(User $actor, Role $role, array $customNames): array
    {
        Gate::forUser($actor)->authorize('permissions.assign');

        if ($role->name === RoleName::Superadmin->value) {
            throw ValidationException::withMessages(['permissions' => 'Le rôle superadmin a déjà tous les accès.']);
        }

        $customs = Permission::custom()->whereIn('name', $customNames)->pluck('name')->all();
        $invalid = array_values(array_diff($customNames, $customs));

        if ($invalid !== []) {
            throw ValidationException::withMessages(['permissions' => 'Seules les permissions créées depuis l\'interface s\'attribuent ici : '.implode(', ', $invalid).'.']);
        }

        return DB::transaction(function () use ($actor, $role, $customs) {
            $before = $role->permissions()->pluck('name')->all();
            $manifestKept = $role->permissions()->where('source', Permission::SOURCE_MANIFEST)->pluck('name')->all();

            $role->syncPermissions([...$manifestKept, ...$customs]);
            $after = $role->permissions()->pluck('name')->all();

            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));

            if ($added !== [] || $removed !== []) {
                $this->logger->log(
                    'permissions.role_synced',
                    'permissions',
                    "Permissions personnalisées modifiées — rôle {$role->name}",
                    $role,
                    old: ['permissions' => $before],
                    new: ['permissions' => $after],
                    actor: $actor,
                    subjectLabel: $role->name,
                );
            }

            return ['added' => $added, 'removed' => $removed];
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertValidName(string $name): void
    {
        $max = config('authorization.name_max_length');

        if ($name === '' || strlen($name) > $max || ! preg_match(config('authorization.name_pattern'), $name)) {
            throw ValidationException::withMessages(['name' => "Le nom doit suivre le format module.action (minuscules, chiffres et tirets bas), {$max} caractères au plus."]);
        }

        [$module] = explode('.', $name, 2);
        if (in_array($module, config('authorization.reserved_modules', []), true)) {
            throw ValidationException::withMessages(['name' => "Le module « {$module} » est réservé : ses permissions ne se créent pas depuis l'interface."]);
        }

        if ($this->registry->has($name) || Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
            throw ValidationException::withMessages(['name' => 'Cette permission existe déjà.']);
        }
    }

    /** Vide le cache des permissions après une écriture faite hors des modèles. */
    public function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
