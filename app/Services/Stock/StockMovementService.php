<?php

namespace App\Services\Stock;

use App\Enums\MouvementType;
use App\Models\Article;
use App\Models\MouvementStock;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Seule porte d'écriture du stock (cahier §15, RG09, CLAUDE.md D-M2, D-M3) : verrouille l'article, vérifie que le
 * solde ne devient pas négatif (RG04), écrit le mouvement (journal non modifiable) et met à jour le stock en cache,
 * dans la transaction de l'appelant.
 */
class StockMovementService
{
    /**
     * @param  float|string  $quantity  signée : + entre en stock, − en sort
     *
     * @throws ValidationException si le stock deviendrait négatif
     */
    public function record(
        int $articleId,
        float|string $quantity,
        MouvementType $type,
        Model $source,
        string $reference,
        User $user,
        string $movedOn,
        ?string $reason = null,
    ): MouvementStock {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Un mouvement de stock doit être écrit dans une transaction.');
        }

        $article = Article::withTrashed()->whereKey($articleId)->lockForUpdate()->firstOrFail();
        $balance = round((float) $article->stock_quantity + (float) $quantity, 3);

        if ($balance < 0) {
            throw ValidationException::withMessages([
                'stock' => "Stock insuffisant pour « {$article->label()} » : disponible ".Quantity::format($article->stock_quantity).', demandé '.Quantity::format(abs((float) $quantity)).'.',
            ]);
        }

        $mouvement = MouvementStock::create([
            'article_id' => $article->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'reference' => $reference,
            'moved_on' => $movedOn,
            'user_id' => $user->id,
            'reason' => $reason,
        ]);

        // Sans événement de modèle : le mouvement est lui-même la trace (pas de ligne d'audit « article modifié »).
        $article->forceFill(['stock_quantity' => $balance])->saveQuietly();

        return $mouvement;
    }

    /**
     * Écarts entre le stock en cache et la somme des mouvements (RG09). Vide = cohérent.
     *
     * @return list<array{article_id: int, reference: string, cached: string, computed: string}>
     */
    public function inconsistencies(): array
    {
        $sums = MouvementStock::query()->selectRaw('article_id, SUM(quantity) as total')->groupBy('article_id')->pluck('total', 'article_id');

        return Article::withTrashed()->get(['id', 'reference', 'stock_quantity'])
            ->filter(fn (Article $article) => round((float) $article->stock_quantity, 3) !== round((float) ($sums[$article->id] ?? 0), 3))
            ->map(fn (Article $article) => [
                'article_id' => $article->id, 'reference' => $article->reference,
                'cached' => (string) $article->stock_quantity, 'computed' => (string) ($sums[$article->id] ?? 0),
            ])->values()->all();
    }
}
