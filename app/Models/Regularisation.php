<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Régularisation de stock (RG11) : écrite uniquement par RegularisationService, jamais modifiée ni supprimée.
 */
#[Fillable(['number', 'regularized_on', 'article_id', 'old_balance', 'new_balance', 'quantity', 'reason', 'user_id'])]
class Regularisation extends Model
{
    use LogsActivity;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une régularisation est non modifiable : faites-en une nouvelle.'));
        static::deleting(fn () => throw new LogicException('Une régularisation est non supprimable.'));
    }

    protected function casts(): array
    {
        return [
            'regularized_on' => 'date',
            'old_balance' => 'decimal:3',
            'new_balance' => 'decimal:3',
            'quantity' => 'decimal:3',
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

    public function activityNoun(): string
    {
        return 'Régularisation';
    }

    public function activityLabel(): string
    {
        return $this->number.' · '.($this->article?->reference ?? '#'.$this->article_id).' : '.Quantity::format($this->old_balance).' → '.Quantity::format($this->new_balance);
    }
}
