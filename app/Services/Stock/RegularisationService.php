<?php

namespace App\Services\Stock;

use App\Enums\MouvementType;
use App\Models\Article;
use App\Models\Regularisation;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Documents\DocumentNumberService;
use App\Support\DocumentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Régularisation (cahier §16, Annexe B, RG11) : le magasinier saisit le stock réellement constaté et le motif ;
 * l'écart devient un mouvement « régularisation » numéroté REG-AAAA-NNNN, avec l'ancienne et la nouvelle valeur.
 */
class RegularisationService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StockMovementService $stock,
        private readonly ActivityLogger $logger,
    ) {}

    public function regularize(User $user, Article $article, float $newBalance, string $reason, string $date): Regularisation
    {
        return DB::transaction(function () use ($user, $article, $newBalance, $reason, $date) {
            $article = Article::whereKey($article->getKey())->lockForUpdate()->firstOrFail();
            $old = round((float) $article->stock_quantity, 3);
            $delta = round($newBalance - $old, 3);

            if (abs($delta) < 0.0005) {
                throw ValidationException::withMessages(['new_balance' => 'Le stock constaté est égal au stock calculé : aucune régularisation à faire.']);
            }

            $regularisation = Regularisation::create([
                'number' => $this->numbers->next(app(DocumentSettings::class)->prefix('regularisation'), (int) substr($date, 0, 4)),
                'regularized_on' => $date,
                'article_id' => $article->id,
                'old_balance' => $old,
                'new_balance' => $newBalance,
                'quantity' => $delta,
                'reason' => $reason,
                'user_id' => $user->id,
            ]);

            $this->stock->record($article->id, $delta, MouvementType::Regularisation, $regularisation, $regularisation->number, $user, $date, $reason);

            $this->logger->log('stock.regularized', 'stock', "Régularisation {$regularisation->number} — {$article->label()}", $article,
                old: ['stock' => $old], new: ['stock' => $newBalance, 'motif' => $reason, 'numero' => $regularisation->number], actor: $user);

            return $regularisation;
        });
    }
}
