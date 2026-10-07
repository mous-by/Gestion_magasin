<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ActivityChannel;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AuditFilterRequest;
use App\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Journal d'audit en lecture seule (permission `audit.view` réservée au superadmin). La consultation elle-même n'est
 * pas journalisée (les lectures ne le sont pas, CLAUDE.md §4). Le détail d'une ligne s'ouvre en modale.
 */
class AuditController extends Controller
{
    private const PER_PAGE = 25;

    public function index(AuditFilterRequest $request): View
    {
        $filters = $request->validated();

        $logs = ActivityLog::query()
            ->when($filters['author'] ?? null, fn ($query, $value) => $query->where('user_name', 'like', '%'.$this->escapeLike($value).'%'))
            ->when($filters['module'] ?? null, fn ($query, $value) => $query->where('module', $value))
            ->when($filters['action'] ?? null, fn ($query, $value) => $query->where('action', 'like', '%'.$this->escapeLike($value).'%'))
            ->when($filters['channel'] ?? null, fn ($query, $value) => $query->where('channel', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()))
            ->when($request->boolean('superadmin'), fn ($query) => $query->where('user_role', RoleName::Superadmin->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'details' => $logs->getCollection()->mapWithKeys(fn (ActivityLog $log) => [$log->id => $this->detail($log)]),
            'modules' => ActivityLog::query()->distinct()->orderBy('module')->pluck('module'),
            'channels' => collect(ActivityChannel::cases())->mapWithKeys(fn (ActivityChannel $channel) => [$channel->value => $this->channelLabel($channel)]),
            'filters' => $filters + ['superadmin' => $request->boolean('superadmin')],
        ]);
    }

    /**
     * Données de la modale de détail d'une ligne (affichées côté navigateur avec textContent : elles peuvent contenir
     * du texte saisi par un tiers, comme un numéro tenté à la connexion).
     *
     * @return array<string, mixed>
     */
    private function detail(ActivityLog $log): array
    {
        return [
            'date' => $log->created_at?->format('d/m/Y H:i:s'),
            'author' => $log->user_name ?? 'Non connecté',
            'role' => $this->roleLabel($log->user_role),
            'superadmin' => $log->isBySuperadmin(),
            'module' => $log->module,
            'action' => $log->action,
            'description' => $log->description,
            'subject' => $log->subject_label ? $log->subject_label.' ('.$log->subject_type.' #'.$log->subject_id.')' : null,
            'channel' => $this->channelLabel($log->channel),
            'ip' => $log->ip_address,
            'agent' => $log->user_agent,
            'old' => $log->old_values ?? [],
            'new' => $log->new_values ?? [],
        ];
    }

    private function roleLabel(?string $role): ?string
    {
        return $role === null ? null : (RoleName::tryFrom($role)?->label() ?? $role);
    }

    private function channelLabel(?ActivityChannel $channel): string
    {
        return match ($channel) {
            ActivityChannel::Web => 'Web',
            ActivityChannel::Console => 'Console',
            null => '—',
        };
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
