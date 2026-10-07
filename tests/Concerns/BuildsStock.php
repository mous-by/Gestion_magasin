<?php

namespace Tests\Concerns;

use App\Models\Article;
use App\Models\Entree;
use App\Models\Fournisseur;
use App\Models\User;
use App\Services\Entrees\EntreeService;

/**
 * Met du stock en place comme l'application : une entrée validée par EntreeService (jamais d'écriture directe du stock).
 */
trait BuildsStock
{
    /**
     * @param  array<int, float|int>  $quantities  [article_id => quantité acceptée]
     */
    protected function receive(User $user, array $quantities, string $date = '2026-10-02'): Entree
    {
        $service = app(EntreeService::class);

        $entree = $service->saveDraft($user, [
            'received_on' => $date,
            'fournisseur_id' => Fournisseur::factory()->create()->id,
            'lines' => collect($quantities)->map(fn ($qty, $id) => ['article_id' => $id, 'delivered_qty' => $qty, 'accepted_qty' => $qty])->values()->all(),
        ]);

        return $service->validate($user, $entree);
    }

    protected function stockOf(Article $article): float
    {
        return (float) $article->fresh()->stock_quantity;
    }
}
