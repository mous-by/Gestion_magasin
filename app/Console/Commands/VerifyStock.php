<?php

namespace App\Console\Commands;

use App\Services\Stock\StockMovementService;
use Illuminate\Console\Command;

/**
 * Contrôle RG09 : le stock en cache de chaque article est égal à la somme de ses mouvements (CLAUDE.md D-M3).
 * Code de sortie 1 en cas d'écart (utilisable dans une tâche planifiée ou une supervision).
 */
class VerifyStock extends Command
{
    protected $signature = 'stock:verify';

    protected $description = 'Vérifie que le stock de chaque article correspond à la somme de ses mouvements (RG09)';

    public function handle(StockMovementService $stock): int
    {
        $issues = $stock->inconsistencies();

        if ($issues === []) {
            $this->info('Stock cohérent : chaque article correspond à la somme de ses mouvements.');

            return self::SUCCESS;
        }

        $this->error(count($issues).' article(s) incohérent(s) :');
        $this->table(['Article', 'Stock en cache', 'Somme des mouvements'], array_map(fn ($i) => [$i['reference'], $i['cached'], $i['computed']], $issues));

        return self::FAILURE;
    }
}
