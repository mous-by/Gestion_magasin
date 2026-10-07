<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une sortie (cahier §13) : article, quantité, stock disponible figé à la validation, observation.
 */
#[Fillable(['article_id', 'quantity', 'stock_before', 'observation'])]
class SortieDetail extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'stock_before' => 'decimal:3'];
    }

    public function sortie(): BelongsTo
    {
        return $this->belongsTo(Sortie::class)->withTrashed();
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    public function activityNoun(): string
    {
        return 'Ligne de sortie';
    }

    public function activityLabel(): string
    {
        return ($this->sortie?->displayNumber() ?? 'Sortie').' · '.($this->article?->reference ?? '#'.$this->article_id);
    }
}
