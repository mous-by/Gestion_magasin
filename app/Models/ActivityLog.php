<?php

namespace App\Models;

use App\Enums\ActivityChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Ligne du journal d'audit (D11) : écrite uniquement par ActivityLogger, jamais modifiée ni supprimée.
 * Les mises à jour de masse (`update()` / `delete()` sur le builder) ne déclenchent pas les événements de
 * modèle : elles sont interdites (test d'architecture) et, en production, le compte applicatif n'a pas les
 * droits UPDATE/DELETE sur cette table.
 */
#[Fillable([
    'user_id', 'user_name', 'user_role', 'action', 'module', 'description',
    'subject_type', 'subject_id', 'subject_label', 'old_values', 'new_values', 'channel', 'ip_address', 'user_agent',
])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Nom de table imposé par le cahier des charges (§24). */
    protected $table = 'audit_logs';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'audit est non modifiable.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'audit est non supprimable.'));
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'channel' => ActivityChannel::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isBySuperadmin(): bool
    {
        return $this->user_role === 'superadmin';
    }
}
