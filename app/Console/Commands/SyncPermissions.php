<?php

namespace App\Console\Commands;

use App\Services\Permissions\PermissionSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * À lancer à chaque déploiement, après `php artisan migrate`. Idempotente (voir PermissionSynchronizer).
 */
#[Signature('permissions:sync')]
#[Description('Synchronise les rôles et les permissions avec les manifestes config/modules/*.php')]
class SyncPermissions extends Command
{
    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $report = $synchronizer->sync();

        $this->components->info('Synchronisation des permissions terminée.');
        $this->line(sprintf('  Rôles créés : %d | Permissions créées : %d | adoptées : %d | rôles mis à jour : %d', count($report['roles_created']), count($report['created']), count($report['adopted']), count($report['roles'])));

        foreach ($report['adopted'] as $name) {
            $this->components->warn("Permission « {$name} » créée depuis l'interface, désormais déclarée par un manifeste (adoptée).");
        }
        foreach ($report['orphans'] as $name) {
            $this->components->warn("Permission orpheline « {$name} » : absente des manifestes, conservée (rien n'est supprimé).");
        }

        return self::SUCCESS;
    }
}
