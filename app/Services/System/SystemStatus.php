<?php

namespace App\Services\System;

use App\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Informations de la page Système, en lecture seule. Aucun secret n'en sort : ni clé d'application, ni identifiant
 * ou mot de passe de base, ni contenu des tâches échouées (seulement la première ligne de l'erreur).
 */
class SystemStatus
{
    /** Nombre de tâches échouées listées. */
    private const FAILED_LIMIT = 10;

    /**
     * Actions de maintenance autorisées (liste blanche) : rien de destructeur, chaque action se régénère toute seule.
     *
     * @var array<string, array{label: string, command: string, audit: string, help: string}>
     */
    public const TASKS = [
        'cache' => [
            'label' => 'Vider le cache applicatif',
            'command' => 'cache:clear',
            'audit' => 'system.cache_cleared',
            'help' => 'Supprime les données mises en cache. Remet aussi à zéro les compteurs de tentatives de connexion.',
        ],
        'views' => [
            'label' => 'Vider les vues compilées',
            'command' => 'view:clear',
            'audit' => 'system.views_cleared',
            'help' => 'Supprime les vues Blade compilées ; elles se recompilent à la prochaine visite.',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function environment(): array
    {
        $synced = ActivityLog::query()->where('action', 'system.permissions_synced')->max('created_at');

        return [
            'name' => config('app.name'),
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'permissions_synced_at' => $synced ? Carbon::parse($synced)->format('d/m/Y H:i') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function database(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();

            return [
                'ok' => true,
                'driver' => $connection->getDriverName(),
                'version' => (string) ($connection->selectOne('select version() as version')->version ?? '—'),
                'name' => $connection->getDatabaseName(),
                'tables' => count(Schema::getTables(schema: $connection->getDatabaseName())),
                'migrations' => Schema::hasTable('migrations') ? DB::table('migrations')->count() : 0,
            ];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function queue(): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        $jobsTable = config("queue.connections.{$connection}.table", 'jobs');
        $failedTable = config('queue.failed.table', 'failed_jobs');
        $hasFailedTable = Schema::hasTable($failedTable);

        return [
            'connection' => $connection,
            'driver' => $driver,
            // Le nombre de tâches en attente n'est connu que pour la file en base.
            'pending' => $driver === 'database' && Schema::hasTable($jobsTable) ? DB::table($jobsTable)->count() : null,
            'failed_total' => $hasFailedTable ? DB::table($failedTable)->count() : 0,
            'failed' => $hasFailedTable ? $this->latestFailed($failedTable) : [],
            'cache' => config('cache.default'),
            'session' => config('session.driver'),
        ];
    }

    /**
     * @return list<array{uuid: string, queue: string, connection: string, failed_at: string, error: string}>
     */
    private function latestFailed(string $table): array
    {
        return DB::table($table)
            ->orderByDesc('id')
            ->limit(self::FAILED_LIMIT)
            ->get(['uuid', 'connection', 'queue', 'failed_at', 'exception'])
            ->map(fn ($row) => [
                'uuid' => (string) $row->uuid,
                'queue' => (string) $row->queue,
                'connection' => (string) $row->connection,
                'failed_at' => Carbon::parse($row->failed_at)->format('d/m/Y H:i'),
                'error' => Str::limit(Str::before((string) $row->exception, "\n"), 200),
            ])
            ->all();
    }
}
