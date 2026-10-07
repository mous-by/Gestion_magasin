<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\UniteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unité de mesure (cahier §9). Une unité utilisée par un article ne se supprime pas : elle se désactive.
 */
#[Fillable(['name', 'symbol', 'allows_decimal', 'is_active'])]
class Unite extends Model
{
    /** @use HasFactory<UniteFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['allows_decimal' => 'boolean', 'is_active' => 'boolean'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function activityNoun(): string
    {
        return 'Unité';
    }
}
