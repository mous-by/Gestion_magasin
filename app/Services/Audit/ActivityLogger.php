<?php

namespace App\Services\Audit;

use App\Enums\ActivityChannel;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Écriture synchrone dans audit_logs, dans la même transaction que l'action (D11).
 * L'auteur est figé au moment de l'action : nom et rôle.
 */
class ActivityLogger
{
    /** Clés jamais écrites dans l'audit, quel que soit leur niveau d'imbrication. */
    private const SENSITIVE = ['password', 'password_confirmation', 'current_password', 'remember_token', 'token', '_token'];

    private const EVENT_LABELS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'restored' => 'Restauration',
    ];

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?User $actor = null,
        ?ActivityChannel $channel = null,
        ?string $subjectLabel = null,
    ): ActivityLog {
        $actor ??= Auth::user();
        $channel ??= ActivityChannel::current();
        $inConsole = $channel === ActivityChannel::Console;

        return ActivityLog::create([
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name,
            'user_role' => $actor?->roleName()?->value,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ?? ($subject && method_exists($subject, 'activityLabel') ? Str::limit($subject->activityLabel(), 255, '') : null),
            'old_values' => $this->sanitize($old) ?: null,
            'new_values' => $this->sanitize($new) ?: null,
            'channel' => $channel,
            'ip_address' => $inConsole ? null : request()->ip(),
            'user_agent' => $inConsole ? null : request()->userAgent(),
        ]);
    }

    /**
     * Événement de cycle de vie d'un modèle (utilisé par le trait LogsActivity).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function model(Model $model, string $event, array $old = [], array $new = []): ActivityLog
    {
        $module = $model->activityModule();
        $description = (self::EVENT_LABELS[$event] ?? $event).' — '.$model->activityNoun().' « '.$model->activityLabel().' »';

        return $this->log("{$module}.{$event}", $module, $description, $model, $old, $new);
    }

    /**
     * Lecture de données sensibles : non journalisée par défaut dans le socle, chaque module décide (DECISIONS point 4).
     */
    public function read(string $module, string $description, ?Model $subject = null): ActivityLog
    {
        return $this->log("{$module}.read", $module, $description, $subject);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array($key, self::SENSITIVE, true)) {
                continue;
            }
            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }
}
