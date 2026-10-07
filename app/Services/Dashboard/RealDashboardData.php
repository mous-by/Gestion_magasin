<?php

namespace App\Services\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Entree;
use App\Models\MouvementStock;
use App\Models\Regularisation;
use App\Models\Sortie;
use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use App\Services\Stock\StockMovementService;
use App\Support\DocumentSettings;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Tableau de bord (cahier §8) : chaque rôle ne voit que ce qui le concerne, section par section, chacune derrière
 * une permission (jamais une donnée affichée sans le droit de la consulter) :
 *  - Magasin (articles, entrées, sorties, stock) : le magasinier — situation du jour, tâches, alertes, activité ;
 *  - Comptes et accès (users.view) et Exploitation (exploitation.view) : l'administrateur technique ;
 *  - Supervision (superadmin seul) : activité par agent, journal, contrôle des comptes superadmin.
 * Le superadmin, qui passe toutes les permissions (Gate::before), a donc la vue la plus large.
 */
class RealDashboardData
{
    /** Âge maximal d'une sauvegarde avant alerte (sauvegarde quotidienne + marge). */
    private const BACKUP_MAX_AGE_HOURS = 26;

    public function __construct(private readonly DatabaseBackup $backups, private readonly StockMovementService $stock, private readonly DocumentSettings $settings) {}

    /**
     * @return array{heading: string, alerts: list<array<string, string>>, tasks: list<array<string, mixed>>, quick: list<array<string, mixed>>, chart: array<string, mixed>|null, sections: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $dashboard = ['heading' => $this->heading($user), 'alerts' => [], 'tasks' => [], 'quick' => $this->quick($user), 'chart' => null, 'sections' => []];

        $this->magasin($user, $dashboard);
        if ($user->can('users.view')) {
            $this->comptes($user, $dashboard);
        }
        if ($user->can('exploitation.view')) {
            $this->exploitation($dashboard);
        }
        if ($user->isSuperadmin()) {
            $this->supervision($dashboard);
        }

        return $dashboard;
    }

    /**
     * Pastilles du menu : brouillons à valider, écarts à traiter, articles à réapprovisionner.
     *
     * @return array<string, int>
     */
    public function sidebarBadges(User $user): array
    {
        $badges = [];

        if ($user->can('entrees.validate') || $user->can('entrees.resolve')) {
            $badges['entrees'] = ($user->can('entrees.validate') ? Entree::where('status', DocumentStatus::Brouillon->value)->count() : 0)
                + ($user->can('entrees.resolve') ? Entree::pendingDiscrepancy()->count() : 0);
        }
        if ($user->can('sorties.validate')) {
            $badges['sorties'] = Sortie::where('status', DocumentStatus::Brouillon->value)->count();
        }
        if ($user->can('stock.view')) {
            $badges['stock'] = $this->lowStock()->count();
        }

        return array_filter($badges);
    }

    /**
     * Notifications de la cloche : uniquement ce que l'utilisateur peut traiter ou consulter.
     *
     * @return list<array{icon: string, class: string, text: string, link: string}>
     */
    public function notifications(User $user): array
    {
        $items = [];
        $add = function (int $count, string $icon, string $class, string $text, string $route, array $params = []) use (&$items) {
            if ($count > 0 && Route::has($route)) {
                $items[] = ['icon' => $icon, 'class' => $class, 'text' => $text, 'link' => route($route, $params)];
            }
        };

        if ($user->can('stock.view')) {
            $out = Article::active()->where('stock_quantity', '<=', 0)->count();
            $low = $this->lowStock()->where('stock_quantity', '>', 0)->count();
            $add($out, 'bx-block', 'text-danger', "{$out} article(s) en rupture de stock.", 'stock.index', ['filtre' => 'rupture']);
            $add($low, 'bx-trending-down', 'text-warning', "{$low} article(s) sous le seuil minimum.", 'stock.index', ['filtre' => 'sous-seuil']);
        }
        if ($user->can('entrees.resolve')) {
            $gaps = Entree::pendingDiscrepancy()->count();
            $add($gaps, 'bx-error', 'text-danger', "{$gaps} écart(s) de réception à traiter.", 'entrees.index', ['filtre' => 'ecart-a-traiter']);
        }
        if ($user->can('entrees.validate')) {
            $drafts = Entree::where('status', DocumentStatus::Brouillon->value)->count();
            $add($drafts, 'bx-log-in-circle', 'text-primary', "{$drafts} entrée(s) en brouillon à valider.", 'entrees.index');
        }
        if ($user->can('sorties.validate')) {
            $drafts = Sortie::where('status', DocumentStatus::Brouillon->value)->count();
            $add($drafts, 'bx-log-out-circle', 'text-primary', "{$drafts} sortie(s) en brouillon à valider.", 'sorties.index');
        }
        if ($user->can('exploitation.view')) {
            $backup = $this->backupState();
            if (! $backup['ok']) {
                $items[] = ['icon' => 'bx-data', 'class' => 'text-danger', 'text' => $backup['message'], 'link' => route('home')];
            }
        }

        return $items;
    }

    /** Magasin (cahier §8) : chaque bloc derrière la permission de son module. */
    private function magasin(User $user, array &$dashboard): void
    {
        $validated = DocumentStatus::Validee->value;
        $today = now()->toDateString();
        $month = now()->startOfMonth()->toDateString();
        $cards = [];
        $tables = [];

        if ($user->can('stock.view')) {
            $dashboard['tasks'][] = $this->task('Articles en rupture', 'Stock égal à zéro', Article::active()->where('stock_quantity', '<=', 0)->count(), 'bx bx-block', 'danger', 'stock.index', ['filtre' => 'rupture']);
            $dashboard['tasks'][] = $this->task('Sous le seuil', 'À réapprovisionner', $this->lowStock()->where('stock_quantity', '>', 0)->count(), 'bx bx-trending-down', 'warning', 'stock.index', ['filtre' => 'sous-seuil']);
        }
        if ($user->can('entrees.resolve')) {
            $dashboard['tasks'][] = $this->task('Écarts à traiter', 'Suite à donner aux livraisons', Entree::pendingDiscrepancy()->count(), 'bx bx-error', 'danger', 'entrees.index', ['filtre' => 'ecart-a-traiter']);
        }
        if ($user->can('entrees.validate')) {
            $dashboard['tasks'][] = $this->task('Entrées à valider', 'Brouillons sans effet sur le stock', Entree::where('status', DocumentStatus::Brouillon->value)->count(), 'bx bx-log-in-circle', 'primary', 'entrees.index');
        }
        if ($user->can('sorties.validate')) {
            $dashboard['tasks'][] = $this->task('Sorties à valider', 'Brouillons sans effet sur le stock', Sortie::where('status', DocumentStatus::Brouillon->value)->count(), 'bx bx-log-out-circle', 'success', 'sorties.index');
        }

        if ($user->can('articles.view')) {
            $cards[] = $this->card('Articles actifs', (string) Article::active()->count(), Article::count().' au catalogue', 'bx bx-package', 'primary');
        }
        if ($user->can('entrees.view')) {
            $entrees = Entree::where('status', $validated);
            $cards[] = $this->card('Entrées du jour', (string) (clone $entrees)->whereDate('received_on', $today)->count(), (clone $entrees)->whereDate('received_on', '>=', $month)->count().' ce mois', 'bx bx-log-in-circle', 'success');
        }
        if ($user->can('sorties.view')) {
            $sorties = Sortie::where('status', $validated);
            $cards[] = $this->card('Sorties du jour', (string) (clone $sorties)->whereDate('issued_on', $today)->count(), (clone $sorties)->whereDate('issued_on', '>=', $month)->count().' ce mois', 'bx bx-log-out-circle', 'info');
        }
        if ($user->can('stock.view')) {
            $cards[] = $this->card('Sous le seuil', (string) $this->lowStock()->where('stock_quantity', '>', 0)->count(), 'à réapprovisionner', 'bx bx-trending-down', 'warning', true);
            $cards[] = $this->card('En rupture', (string) Article::active()->where('stock_quantity', '<=', 0)->count(), 'stock égal à zéro', 'bx bx-block', 'danger');

            $tables[] = [
                'title' => 'ARTICLES À RÉAPPROVISIONNER', 'icon' => 'bx bx-cart',
                'columns' => ['Référence', 'Désignation', 'Stock', 'Seuil'],
                'rows' => $this->lowStock()->with('unite')->orderBy('stock_quantity')->orderBy('designation')->limit(10)->get()->map(fn (Article $a) => [
                    $a->reference, $a->designation,
                    ['badge' => $a->stockForDisplay().' '.$a->unite?->symbol, 'class' => (float) $a->stock_quantity <= 0 ? 'bg-danger-subtle' : 'bg-warning-subtle'],
                    $a->thresholdForDisplay() ?? '—',
                ])->all(),
            ];
        }
        if ($user->can('entrees.view')) {
            $pending = Entree::pendingDiscrepancy()->count();
            $cards[] = $this->card('Écarts de réception', (string) $pending, $pending ? 'à traiter' : 'tous traités', 'bx bx-error', $pending ? 'danger' : 'secondary');

            $tables[] = [
                'title' => 'DERNIÈRES ENTRÉES', 'icon' => 'bx bx-log-in-circle',
                'columns' => ['Date', 'Référence', 'Fournisseur', 'Articles'],
                'rows' => Entree::with(['fournisseur', 'details'])->where('status', $validated)->latest('validated_at')->limit(5)->get()->map(fn (Entree $e) => [
                    $e->received_on->format('d/m/Y'), $e->number, $e->fournisseur?->name ?? '—',
                    $e->hasPendingDiscrepancy() ? ['badge' => $e->details->count().' · écart à traiter', 'class' => 'bg-danger-subtle'] : (string) $e->details->count(),
                ])->all(),
            ];
            $tables[] = [
                'title' => 'DERNIERS ÉCARTS DE LIVRAISON', 'icon' => 'bx bx-error',
                'columns' => ['Date', 'Entrée', 'Article', 'Livré / accepté', 'Écart'],
                'rows' => Entree::with(['details.article'])->where('status', $validated)->where('has_discrepancy', true)->latest('validated_at')->limit(10)->get()
                    ->flatMap(fn (Entree $e) => $e->details->map(fn ($d) => [$e, $d, $d->discrepancies($e->isPlanned())]))
                    ->filter(fn ($row) => $row[2] !== [])->take(6)
                    ->map(fn ($row) => [
                        $row[0]->received_on->format('d/m/Y'), $row[0]->number, $row[1]->article?->designation ?? '—',
                        Quantity::format($row[1]->delivered_qty).' / '.Quantity::format($row[1]->accepted_qty),
                        ['badge' => implode(', ', $row[2]).($row[0]->discrepancy_resolved_at ? ' · traité' : ''), 'class' => $row[0]->discrepancy_resolved_at ? 'bg-secondary-subtle' : 'bg-warning-subtle'],
                    ])->values()->all(),
            ];
        }
        if ($user->can('sorties.view')) {
            $tables[] = [
                'title' => 'DERNIÈRES SORTIES', 'icon' => 'bx bx-log-out-circle',
                'columns' => ['Date', 'Référence', 'Service', 'Demandeur'],
                'rows' => Sortie::with('service')->where('status', $validated)->latest('validated_at')->limit(5)->get()->map(fn (Sortie $s) => [
                    $s->issued_on->format('d/m/Y'), $s->number, $s->service?->name ?? '—', $s->requester_name,
                ])->all(),
            ];
        }
        if ($user->can('entrees.view') && $user->can('sorties.view')) {
            $dashboard['chart'] = $this->activity(14);
        }

        if ($cards !== [] || $tables !== []) {
            $dashboard['sections'][] = ['title' => 'Magasin', 'icon' => 'bx bx-store-alt', 'cards' => $cards, 'tables' => $tables];
        }
    }

    /** Comptes et accès (users.view) : périmètre visible de l'acteur (User::visibleTo). */
    private function comptes(User $user, array &$dashboard): void
    {
        $visible = fn () => User::query()->visibleTo($user);

        $cards = [
            $this->card('Utilisateurs actifs', (string) $visible()->active()->count(), $visible()->count().' au total', 'bx bx-group', 'primary'),
            $this->card('Comptes désactivés', (string) $visible()->where('is_active', false)->count(), null, 'bx bx-lock-alt', 'secondary'),
            $this->card('Mots de passe temporaires', (string) $visible()->where('must_change_password', true)->count(), 'à changer à la connexion', 'bx bx-key', 'warning', true),
        ];

        $tables = [[
            'title' => 'DERNIÈRES CONNEXIONS', 'icon' => 'bx bx-log-in',
            'columns' => ['Utilisateur', 'Rôle', 'Dernière connexion', 'Statut'],
            'rows' => $visible()->with('roles')->whereNotNull('last_login_at')->orderByDesc('last_login_at')->limit(8)->get()->map(fn (User $u) => [
                $u->name, $u->roleName()?->label() ?? '—', $u->last_login_at->format('d/m/Y H:i'),
                $u->is_active ? ['badge' => 'Actif', 'class' => 'bg-success-subtle'] : ['badge' => 'Désactivé', 'class' => 'bg-secondary-subtle'],
            ])->all(),
        ]];

        $dashboard['sections'][] = ['title' => 'Comptes et accès', 'icon' => 'bx bx-user-circle', 'cards' => $cards, 'tables' => $tables];
    }

    /** Exploitation (exploitation.view) : sauvegarde, cohérence du stock (RG09), paramètres des documents. */
    private function exploitation(array &$dashboard): void
    {
        $backup = $this->backupState();
        $issues = count($this->stock->inconsistencies());
        $logo = $this->settings->logoPath() !== null;
        $unnamed = collect(DocumentSettings::DOCUMENTS)->keys()->flatMap(fn ($doc) => $this->settings->rawSignatures($doc))
            ->filter(fn ($slot) => $slot['label'] !== '' && $slot['name'] === '')->count();

        if (! $backup['ok']) {
            $dashboard['alerts'][] = ['class' => 'alert-danger', 'icon' => 'bx bx-data', 'title' => 'Sauvegarde.', 'text' => $backup['message'].' Lancez « php artisan backup:run » et vérifiez la tâche planifiée (CLAUDE.md §9).'];
        }
        if ($issues > 0) {
            $dashboard['alerts'][] = ['class' => 'alert-danger', 'icon' => 'bx bx-error-alt', 'title' => 'Stock incohérent.', 'text' => "{$issues} article(s) dont le stock ne correspond plus à la somme des mouvements (RG09) : lancez « php artisan stock:verify »."];
        }

        $cards = [
            $this->card('Dernière sauvegarde', $backup['value'], $backup['sub'], 'bx bx-data', $backup['ok'] ? 'success' : 'danger'),
            $this->card('Cohérence du stock', $issues === 0 ? 'Conforme' : "{$issues} écart(s)", 'stock = somme des mouvements', 'bx bx-check-shield', $issues === 0 ? 'success' : 'danger'),
            $this->card('Documents imprimés', $logo ? 'Logo en place' : 'Logo manquant', $unnamed ? "{$unnamed} signature(s) sans nom" : 'signataires renseignés', 'bx bx-file', $logo ? 'info' : 'warning', ! $logo),
        ];

        $dashboard['sections'][] = ['title' => 'Exploitation', 'icon' => 'bx bx-server', 'cards' => $cards, 'tables' => []];
    }

    /** Supervision (superadmin) : comptes superadmin, activité par agent, journal d'audit. */
    private function supervision(array &$dashboard): void
    {
        $activeSuperadmins = User::role(RoleName::Superadmin->value)->active()->count();
        if ($activeSuperadmins < 2) {
            $dashboard['alerts'][] = ['class' => 'alert-warning', 'icon' => 'bx bx-error', 'title' => 'Un seul superadmin actif.', 'text' => 'Créez un second compte superadmin (un compte individuel par développeur) : le dernier superadmin actif est protégé et ne peut pas être désactivé.'];
        }

        $since = now()->subDays(30);
        $validatedBy = fn (string $model) => $model::where('status', DocumentStatus::Validee->value)->where('validated_at', '>=', $since)
            ->selectRaw('validated_by as uid, COUNT(*) as total')->groupBy('validated_by')->pluck('total', 'uid');
        $entrees = $validatedBy(Entree::class);
        $sorties = $validatedBy(Sortie::class);
        $regularisations = Regularisation::where('created_at', '>=', $since)->selectRaw('user_id as uid, COUNT(*) as total')->groupBy('user_id')->pluck('total', 'uid');

        $cards = [
            $this->card('Connexions aujourd\'hui', (string) ActivityLog::where('action', 'auth.login')->where('created_at', '>=', now()->startOfDay())->count(), null, 'bx bx-log-in-circle', 'dark'),
            $this->card('Actions journalisées', (string) ActivityLog::where('created_at', '>=', now()->startOfDay())->count(), 'aujourd\'hui', 'bx bx-list-check', 'primary'),
            $this->card('Mouvements de stock', (string) MouvementStock::where('created_at', '>=', now()->startOfMonth())->count(), 'ce mois', 'bx bx-transfer-alt', 'info'),
            $this->card('Superadmins actifs', (string) $activeSuperadmins, null, 'bx bx-shield-quarter', $activeSuperadmins < 2 ? 'warning' : 'success', $activeSuperadmins < 2),
        ];

        $tables = [
            [
                'title' => 'ACTIVITÉ PAR AGENT (30 JOURS)', 'icon' => 'bx bx-user-check',
                'columns' => ['Agent', 'Rôle', 'Entrées', 'Sorties', 'Régul.', 'Dernière connexion'],
                'rows' => User::with('roles')->orderBy('name')->get()->map(fn (User $u) => [
                    $u->name, $u->roleName()?->label() ?? '—', (string) ($entrees[$u->id] ?? 0), (string) ($sorties[$u->id] ?? 0),
                    (string) ($regularisations[$u->id] ?? 0), $u->last_login_at?->format('d/m/Y H:i') ?? 'jamais',
                ])->all(),
            ],
            [
                'title' => 'DERNIÈRES ACTIONS', 'icon' => 'bx bx-list-check',
                'columns' => ['Date', 'Auteur', 'Action'],
                'rows' => ActivityLog::latest('id')->limit(10)->get()->map(fn (ActivityLog $log) => [
                    $log->created_at->format('d/m/Y H:i'), $log->user_name ?? '—', $log->description,
                ])->all(),
            ],
        ];

        $dashboard['sections'][] = ['title' => 'Supervision', 'icon' => 'bx bx-show', 'cards' => $cards, 'tables' => $tables];
    }

    /**
     * Activité des N derniers jours : entrées et sorties validées par jour (graphique, une seule échelle).
     *
     * @return array{days: list<array{date: string, label: string, entrees: int, sorties: int}>, max: int, total_entrees: int, total_sorties: int}
     */
    private function activity(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $count = fn (string $model, string $dateColumn) => $model::where('status', DocumentStatus::Validee->value)->where($dateColumn, '>=', $from->toDateString())
            ->selectRaw("DATE({$dateColumn}) as day, COUNT(*) as total")->groupBy('day')->pluck('total', 'day');
        $entrees = $count(Entree::class, 'received_on');
        $sorties = $count(Sortie::class, 'issued_on');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $series[] = ['date' => $key, 'label' => $day->format('d/m'), 'entrees' => (int) ($entrees[$key] ?? 0), 'sorties' => (int) ($sorties[$key] ?? 0)];
        }

        return [
            'days' => $series,
            'max' => max(1, ...array_map(fn ($d) => max($d['entrees'], $d['sorties']), $series)),
            'total_entrees' => array_sum(array_column($series, 'entrees')),
            'total_sorties' => array_sum(array_column($series, 'sorties')),
        ];
    }

    /**
     * État de la dernière sauvegarde (fichier le plus récent et dernier résultat de backup:verify).
     *
     * @return array{ok: bool, value: string, sub: string, message: string}
     */
    private function backupState(): array
    {
        $file = $this->backups->latest();
        if ($file === null) {
            return ['ok' => false, 'value' => 'Aucune', 'sub' => 'aucune sauvegarde trouvée', 'message' => 'Aucune sauvegarde de la base n\'a été trouvée.'];
        }

        $at = Carbon::createFromTimestamp(filemtime($file))->setTimezone(config('app.timezone'));
        $hours = (int) $at->diffInHours(now());
        $verification = Cache::get('backup.last_verification');
        $verified = is_array($verification) && $verification['file'] === basename($file);

        if ($hours > self::BACKUP_MAX_AGE_HOURS) {
            return ['ok' => false, 'value' => $at->format('d/m/Y H:i'), 'sub' => "il y a {$hours} h", 'message' => "La dernière sauvegarde date de {$hours} heures."];
        }
        if ($verified && ! $verification['ok']) {
            return ['ok' => false, 'value' => $at->format('d/m/Y H:i'), 'sub' => 'vérification échouée', 'message' => 'La dernière sauvegarde a échoué à la vérification : '.implode(' ', $verification['problems'])];
        }

        return ['ok' => true, 'value' => $at->format('d/m/Y H:i'), 'sub' => $verified ? 'vérifiée' : 'non encore vérifiée', 'message' => ''];
    }

    /** Articles actifs à réapprovisionner : en rupture, ou sous un seuil défini. */
    private function lowStock(): Builder
    {
        return Article::active()->where(fn ($q) => $q->where('stock_quantity', '<=', 0)
            ->orWhere(fn ($q) => $q->whereNotNull('min_threshold')->whereColumn('stock_quantity', '<', 'min_threshold')));
    }

    private function heading(User $user): string
    {
        return match ($user->roleName()) {
            RoleName::Superadmin => 'Supervision générale',
            RoleName::Administrateur => 'Administration technique',
            default => 'Mon magasin aujourd\'hui',
        };
    }

    /**
     * Accès rapides selon les permissions ; « Nouvelle entrée » et « Nouvelle sortie » en tête (cahier §8).
     *
     * @return list<array<string, mixed>>
     */
    private function quick(User $user): array
    {
        $candidates = [
            ['entrees.create', 'entrees.create', 'Nouvelle entrée', 'bx bx-log-in-circle'],
            ['sorties.create', 'sorties.create', 'Nouvelle sortie', 'bx bx-log-out-circle'],
            ['stock.index', 'stock.view', 'État du stock', 'bx bx-transfer-alt'],
            ['rapports.index', 'rapports.view', 'Rapports', 'bx bx-bar-chart-alt-2'],
            ['articles.index', 'articles.view', 'Articles', 'bx bx-package'],
            ['users.index', 'users.view', 'Utilisateurs', 'bx bx-user-circle'],
            ['documents.settings', 'documents.manage', 'Paramètres des documents', 'bx bx-file'],
            ['audit.index', 'audit.view', 'Audit', 'bx bx-list-check'],
            ['system.index', 'system.view', 'Système', 'bx bx-server'],
        ];

        $quick = [];
        foreach ($candidates as [$route, $permission, $label, $icon]) {
            if (Route::has($route) && $user->can($permission)) {
                $quick[] = ['label' => $label, 'icon' => $icon, 'route' => $route, 'params' => []];
            }
        }

        return $quick;
    }

    /**
     * @return array<string, mixed>
     */
    private function task(string $label, string $hint, int $count, string $icon, string $color, string $route, array $params = []): array
    {
        return compact('label', 'hint', 'count', 'icon', 'color', 'route', 'params');
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $label, string $value, ?string $sub, string $icon, string $color, bool $darkText = false): array
    {
        return ['label' => $label, 'value' => $value, 'sub' => $sub, 'icon' => $icon, 'color' => $color, 'darkText' => $darkText];
    }
}
