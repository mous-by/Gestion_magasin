<?php

namespace App\Models;

use App\Enums\MouvementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Mouvement de stock (cahier §15, §16) : écrit uniquement par StockMovementService, jamais modifié ni supprimé
 * (RG05) — comme le journal d'audit. Une erreur se corrige par un contre-mouvement (annulation, régularisation).
 */
#[Fillable(['article_id', 'type', 'quantity', 'balance_after', 'source_type', 'source_id', 'reference', 'moved_on', 'user_id', 'reason'])]
class MouvementStock extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mouvements_stock';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Un mouvement de stock est non modifiable.'));
        static::deleting(fn () => throw new LogicException('Un mouvement de stock est non supprimable.'));
    }

    protected function casts(): array
    {
        return [
            'type' => MouvementType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'moved_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
