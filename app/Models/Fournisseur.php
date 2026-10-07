<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\FournisseurFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fournisseur (cahier §10). L'historique de ses livraisons est celui de ses entrées (phase 4).
 */
#[Fillable(['name', 'address', 'phone', 'email', 'contact_person', 'admin_references', 'is_active'])]
class Fournisseur extends Model
{
    /** @use HasFactory<FournisseurFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Historique des livraisons (cahier §10). */
    public function entrees(): HasMany
    {
        return $this->hasMany(Entree::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function activityNoun(): string
    {
        return 'Fournisseur';
    }
}
