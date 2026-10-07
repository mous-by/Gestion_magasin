<?php

namespace App\Services\Permissions;

use App\Enums\ActivityChannel;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Services\Audit\ActivityLogger;
use App\Support\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Voie A (D6, D23) : synchronise la base avec les manifestes. Idempotente.
 *
 * - crée les rôles et les permissions manquantes (source = manifest) ;
 * - resynchronise les permissions par défaut de chaque rôle avec les manifestes ;
 * - ne touche JAMAIS aux permissions `custom` (créées depuis l'interface), ni à leurs attributions,
 *   ni aux exceptions directes des utilisateurs ;
 * - signale les orphelines (source = manifest, absentes des manifestes) sans les supprimer.
 */
class PermissionSynchronizer
{
    public function __construct(private readonly ModuleRegistry $registry, private readonly ActivityLogger $logger) {}

    /**
     * @return array{roles_created: list<string>, created: list<string>, adopted: list<string>, roles: array<string, array{added: list<string>, removed: list<string>}>, orphans: list<string>}
     */
    public function sync(): array
    {
        $this->registry->assertValid();

        $report = ['roles_created' => [], 'created' => [], 'adopted' => [], 'roles' => [], 'orphans' => []];

        DB::transaction(function () use (&$report) {
            $this->ensureRoles($report);
            Permission::withoutActivityLog(function () use (&$report) {
                $this->syncPermissions($report);
            });
            $this->syncRoles($report);
            $report['orphans'] = Permission::manifest()->whereNotIn('name', $this->registry->names())->orderBy('name')->pluck('name')->all();

            if ($this->hasChanges($report)) {
                $this->logger->log(
                    'system.permissions_synced',
                    'system',
                    sprintf('Synchronisation des permissions : %d créée(s), %d adoptée(s), %d rôle(s) mis à jour', count($report['created']), count($report['adopted']), count($report['roles'])),
                    new: array_filter(['created' => $report['created'], 'adopted' => $report['adopted'], 'roles' => $report['roles']]),
                    channel: ActivityChannel::current(),
                );
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $report;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function ensureRoles(array &$report): void
    {
        foreach (RoleName::cases() as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName->value, 'guard_name' => 'web']);
            if ($role->wasRecentlyCreated) {
                $report['roles_created'][] = $roleName->value;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function syncPermissions(array &$report): void
    {
        foreach ($this->registry->permissions() as $name => $definition) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();

            if ($permission === null) {
                Permission::create(['name' => $name, 'guard_name' => 'web', 'source' => Permission::SOURCE_MANIFEST]);
                $report['created'][] = $name;
            } elseif ($permission->isCustom()) {
                // Un manifeste déclare désormais une permission créée avant depuis l'interface : le manifeste fait foi.
                $permission->update(['source' => Permission::SOURCE_MANIFEST]);
                $report['adopted'][] = $name;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function syncRoles(array &$report): void
    {
        foreach (RoleName::cases() as $roleName) {
            // Le superadmin passe par Gate::before : aucune permission n'est attachée.
            if ($roleName === RoleName::Superadmin) {
                continue;
            }

            $role = Role::findByName($roleName->value, 'web');
            $current = $role->permissions()->pluck('name')->all();
            $custom = $role->permissions()->where('source', Permission::SOURCE_CUSTOM)->pluck('name')->all();

            $target = array_values(array_unique([...$this->registry->roleDefaults($roleName), ...$custom]));

            $added = array_values(array_diff($target, $current));
            $removed = array_values(array_diff($current, $target));

            if ($added !== [] || $removed !== []) {
                $role->syncPermissions($target);
                $report['roles'][$roleName->value] = ['added' => $added, 'removed' => $removed];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function hasChanges(array $report): bool
    {
        return $report['created'] !== [] || $report['adopted'] !== [] || $report['roles'] !== [];
    }
}
