<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\SortieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sortie de matériel (cahier §13) : brouillon → validée → annulée (CLAUDE.md D-M1). Toute écriture passe par
 * SortieService (contrôle du stock sous verrou, numérotation, mouvements, audit).
 */
#[Fillable(['issued_on', 'requester_name', 'service_id', 'reason', 'paper_reference', 'observation', 'created_by'])]
class Sortie extends Model
{
    /** @use HasFactory<SortieFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'status' => DocumentStatus::class,
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function details(): HasMany
    {
        return $this->hasMany(SortieDetail::class)->orderBy('id');
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

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Brouillon;
    }

    public function isValidated(): bool
    {
        return $this->status === DocumentStatus::Validee;
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'Brouillon n° '.$this->getKey();
    }

    public function activityNoun(): string
    {
        return 'Sortie';
    }

    public function activityLabel(): string
    {
        return $this->displayNumber();
    }
}
