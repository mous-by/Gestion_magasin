<?php

namespace App\Models\Concerns;

use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tout modèle métier utilise ce trait (test d'architecture) : création, modification (anciennes et nouvelles
 * valeurs des seuls champs modifiés), suppression et restauration sont journalisées automatiquement.
 * Les actions métier (validation, paiement…) s'appellent explicitement via ActivityLogger::log().
 *
 * @mixin Model
 */
trait LogsActivity
{
    /** Lorsque > 0, la journalisation automatique est suspendue (synchronisation des permissions : un seul résumé est écrit). */
    protected static int $activityLoggingPaused = 0;

    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->logActivityEvent('created', [], $model->activityAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = $model->activityAttributes($model->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->activityAttributes($model->getRawOriginal()), $changes);
            $model->logActivityEvent('updated', $old, $changes);
        });

        static::deleted(fn (Model $model) => $model->logActivityEvent('deleted', $model->activityAttributes($model->getAttributes()), []));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => $model->logActivityEvent('restored', [], []));
        }
    }

    /**
     * Exécute $callback sans journalisation automatique de ce modèle.
     */
    public static function withoutActivityLog(callable $callback): mixed
    {
        static::$activityLoggingPaused++;

        try {
            return $callback();
        } finally {
            static::$activityLoggingPaused--;
        }
    }

    /** Nom du module d'audit (par défaut, la table). */
    public function activityModule(): string
    {
        return $this->getTable();
    }

    /** Nom français de l'objet, pour la description (« Utilisateur », « Article »…). */
    public function activityNoun(): string
    {
        return Str::headline(class_basename($this));
    }

    /** Libellé figé de l'objet dans l'audit. */
    public function activityLabel(): string
    {
        return (string) ($this->getAttribute('name') ?? '#'.$this->getKey());
    }

    /**
     * Champs jamais audités.
     *
     * @return list<string>
     */
    public function activityExcept(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function logActivityEvent(string $event, array $old, array $new): void
    {
        if (static::$activityLoggingPaused > 0) {
            return;
        }

        app(ActivityLogger::class)->model($this, $event, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function activityAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip($this->activityExcept()));
    }
}
