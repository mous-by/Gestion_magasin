<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde de la base MySQL / MariaDB (cahier §22) : mysqldump compressé en gzip (sans procédures stockées :
 * l'application n'en utilise aucune), rotation des versions, copie
 * séparée facultative. Le mot de passe passe par la variable d'environnement MYSQL_PWD, jamais sur la ligne de
 * commande (visible dans la liste des processus).
 */
class DatabaseBackup
{
    /** Crée une sauvegarde et renvoie son chemin. */
    public function run(): string
    {
        $db = config('database.connections.'.config('database.default'));
        $dir = config('backup.path');
        File::ensureDirectoryExists($dir);

        $file = $dir.'/'.$db['database'].'_'.now()->format('Ymd_His').'.sql.gz';
        $sql = $file.'.tmp';

        $process = new Process([
            config('backup.mysqldump'), '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--single-transaction', '--default-character-set=utf8mb4', '--result-file='.$sql, $db['database'],
        ], null, ['MYSQL_PWD' => (string) $db['password']]);
        $process->setTimeout(600)->run();

        if (! $process->isSuccessful() || ! is_file($sql)) {
            @unlink($sql);
            throw new RuntimeException('Échec de mysqldump : '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $in = fopen($sql, 'rb');
        $out = gzopen($file, 'wb6');
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        unlink($sql);

        if ($copy = config('backup.copy_path')) {
            File::ensureDirectoryExists($copy);
            File::copy($file, $copy.'/'.basename($file));
        }

        $this->rotate($dir);

        return $file;
    }

    /**
     * Contrôle d'une sauvegarde : archive gzip lisible, fin de dump présente, tables du magasin incluses.
     *
     * @return list<string> problèmes trouvés (vide = sauvegarde exploitable)
     */
    public function verify(?string $file = null): array
    {
        $file ??= $this->latest();
        if ($file === null) {
            return ['Aucune sauvegarde trouvée dans '.config('backup.path').'.'];
        }

        $content = @gzdecode((string) file_get_contents($file));
        if ($content === false || $content === '') {
            return ["Archive illisible : {$file}."];
        }

        $problems = [];
        if (! str_contains($content, '-- Dump completed')) {
            $problems[] = 'Le dump est incomplet (marque de fin absente).';
        }
        foreach (['users', 'articles', 'entrees', 'sorties', 'mouvements_stock', 'audit_logs'] as $table) {
            if (! str_contains($content, "CREATE TABLE `{$table}`")) {
                $problems[] = "Table {$table} absente de la sauvegarde.";
            }
        }

        return $problems;
    }

    public function latest(): ?string
    {
        $files = glob(config('backup.path').'/*.sql.gz') ?: [];
        sort($files);

        return $files === [] ? null : end($files);
    }

    private function rotate(string $dir): void
    {
        $files = glob($dir.'/*.sql.gz') ?: [];
        sort($files);

        foreach (array_slice($files, 0, max(0, count($files) - max(1, config('backup.keep')))) as $old) {
            unlink($old);
        }
    }
}
