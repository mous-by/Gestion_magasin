<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Test de la dernière sauvegarde (cahier §22 « test périodique de restauration », premier niveau) : archive lisible,
 * dump complet, tables du magasin présentes. La restauration complète sur une base de test est décrite au §9 de
 * CLAUDE.md.
 */
class BackupVerify extends Command
{
    protected $signature = 'backup:verify {file? : chemin d\'une sauvegarde (la plus récente par défaut)}';

    protected $description = 'Vérifie que la dernière sauvegarde est exploitable';

    public function handle(DatabaseBackup $backup): int
    {
        $file = $this->argument('file') ?? $backup->latest();
        $problems = $backup->verify($file);

        // Dernier résultat, affiché au tableau de bord (exploitation) sans relire l'archive à chaque visite.
        Cache::forever('backup.last_verification', ['file' => $file ? basename($file) : null, 'ok' => $problems === [], 'problems' => $problems, 'at' => now()->toIso8601String()]);

        if ($problems === []) {
            $this->info('Sauvegarde exploitable : '.$file);

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
