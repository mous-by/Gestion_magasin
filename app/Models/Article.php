<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Quantity;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fiche article (cahier §9) : référence unique (RG01), inactif = plus utilisable dans une nouvelle opération (RG07).
 */
/**
 * `stock_quantity` n'est volontairement pas remplissable : seul StockMovementService le modifie (D-M3, RG09).
 */
#[Fillable(['reference', 'designation', 'category_id', 'unite_id', 'brand_model', 'min_threshold', 'location', 'observation', 'is_active'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['min_threshold' => 'decimal:3', 'stock_quantity' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function unite(): BelongsTo
    {
        return $this->belongsTo(Unite::class)->withTrashed();
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Recherche par référence ou désignation (cahier §17). */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn ($q) => $q->where('reference', 'like', "%{$term}%")->orWhere('designation', 'like', "%{$term}%"));
    }

    /** Libellé affiché partout : « RÉF — Désignation ». */
    public function label(): string
    {
        return "{$this->reference} — {$this->designation}";
    }

    public function stockForDisplay(): string
    {
        return Quantity::format($this->stock_quantity ?? 0);
    }

    /** Situation du stock (cahier §15.1) : rupture, sous le seuil minimum, ou normal. */
    public function stockLevel(): string
    {
        $stock = (float) $this->stock_quantity;

        return match (true) {
            $stock <= 0 => 'rupture',
            $this->min_threshold !== null && $stock < (float) $this->min_threshold => 'sous-seuil',
            default => 'normal',
        };
    }

    public function thresholdForDisplay(): ?string
    {
        return Quantity::format($this->min_threshold);
    }

    public function activityNoun(): string
    {
        return 'Article';
    }

    public function activityLabel(): string
    {
        return $this->label();
    }
}
