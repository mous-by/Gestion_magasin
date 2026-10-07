<?php

namespace App\Models;

use App\Enums\RejectionReason;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une entrée (cahier §11) : quantités prévue (facultative), livrée, acceptée et rejetée. Les écarts du
 * cahier §12 se lisent ici ; seule la quantité acceptée entre en stock (RG02).
 */
#[Fillable(['article_id', 'expected_qty', 'delivered_qty', 'accepted_qty', 'rejected_qty', 'rejection_reason', 'observation'])]
class EntreeDetail extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'expected_qty' => 'decimal:3',
            'delivered_qty' => 'decimal:3',
            'accepted_qty' => 'decimal:3',
            'rejected_qty' => 'decimal:3',
            'rejection_reason' => RejectionReason::class,
        ];
    }

    public function entree(): BelongsTo
    {
        return $this->belongsTo(Entree::class)->withTrashed();
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    /** Livré − prévu : négatif = manquant, positif = excédent, null si aucune quantité prévue. */
    public function deliveryGap(): ?float
    {
        return $this->expected_qty === null ? null : round((float) $this->delivered_qty - (float) $this->expected_qty, 3);
    }

    /**
     * Écarts de la ligne (cahier §12), en clair. `$planned` : l'entrée porte des quantités prévues, donc une
     * ligne sans prévu est un « article non prévu ».
     *
     * @return list<string>
     */
    public function discrepancies(bool $planned): array
    {
        $gap = $this->deliveryGap();
        $list = [];

        if ($planned && $this->expected_qty === null) {
            $list[] = 'Article non prévu';
        }
        if ($gap !== null && $gap < 0) {
            $list[] = 'Manquant';
        }
        if ($gap !== null && $gap > 0) {
            $list[] = 'Excédent';
        }
        if ((float) $this->rejected_qty > 0) {
            $list[] = $this->rejection_reason?->label() ?? 'Rejeté';
        }

        return $list;
    }

    public function activityNoun(): string
    {
        return 'Ligne d\'entrée';
    }

    public function activityLabel(): string
    {
        return ($this->entree?->displayNumber() ?? 'Entrée').' · '.($this->article?->reference ?? '#'.$this->article_id);
    }
}
