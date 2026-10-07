<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\EntreeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Entrée / réception (cahier §11) : brouillon → validée → annulée (CLAUDE.md D-M1). Toute écriture passe par
 * EntreeService (transactions, numérotation, mouvements de stock, audit).
 */
#[Fillable(['received_on', 'fournisseur_id', 'delivery_note_number', 'order_reference', 'observation', 'created_by'])]
class Entree extends Model
{
    /** @use HasFactory<EntreeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'status' => DocumentStatus::class,
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'has_discrepancy' => 'boolean',
            'discrepancy_resolved_at' => 'datetime',
        ];
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class)->withTrashed();
    }

    public function details(): HasMany
    {
        return $this->hasMany(EntreeDetail::class)->orderBy('id');
    }

    public function mouvements(): MorphMany
    {
        return $this->morphMany(MouvementStock::class, 'source');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discrepancy_resolved_by')->withTrashed();
    }

    /** Entrées validées dont l'écart attend encore sa suite (cahier §15.1). */
    public function scopePendingDiscrepancy(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Validee->value)->where('has_discrepancy', true)->whereNull('discrepancy_resolved_at');
    }

    public function hasPendingDiscrepancy(): bool
    {
        return $this->isValidated() && $this->has_discrepancy && $this->discrepancy_resolved_at === null;
    }

    public function scopeStatus(Builder $query, DocumentStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Brouillon;
    }

    public function isValidated(): bool
    {
        return $this->status === DocumentStatus::Validee;
    }

    /** Le numéro n'existe qu'après validation (D-M5) : un brouillon s'affiche par son identifiant. */
    public function displayNumber(): string
    {
        return $this->number ?? 'Brouillon n° '.$this->getKey();
    }

    /** L'entrée porte des quantités prévues : une ligne sans prévu est alors un « article non prévu » (cahier §12). */
    public function isPlanned(): bool
    {
        return $this->details->contains(fn (EntreeDetail $detail) => $detail->expected_qty !== null);
    }

    public function hasDiscrepancy(): bool
    {
        $planned = $this->isPlanned();

        return $this->details->contains(fn (EntreeDetail $detail) => $detail->discrepancies($planned) !== []);
    }

    public function activityNoun(): string
    {
        return 'Entrée';
    }

    public function activityLabel(): string
    {
        return $this->displayNumber();
    }
}
