<?php

namespace App\Console\Commands;

use App\Services\Audit\ActivityLogger;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sauvegarde quotidienne de la base (cahier §22), planifiée dans routes/console.php. Journalisée (RG12).
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Sauvegarde compressée de la base de données (avec rotation et copie séparée)';

    public function handle(DatabaseBackup $backup, ActivityLogger $logger): int
    {
        try {
            $file = $backup->run();
        } catch (Throwable $e) {
            $logger->log('system.backup_failed', 'system', 'Échec de la sauvegarde : '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $logger->log('system.backup', 'system', 'Sauvegarde de la base — '.basename($file), new: ['fichier' => basename($file), 'taille' => filesize($file)]);
        $this->info('Sauvegarde créée : '.$file.' ('.round(filesize($file) / 1024).' Ko)');

        return self::SUCCESS;
    }
}
