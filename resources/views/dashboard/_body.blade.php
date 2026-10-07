{{-- Corps du tableau de bord, commun aux rôles. $dashboard : App\Services\Dashboard\RealDashboardData::for() — chaque
     section n'existe que si l'utilisateur a la permission de son contenu (magasin, comptes, exploitation, supervision). --}}
@foreach ($dashboard['alerts'] as $alert)
    <div class="alert {{ $alert['class'] }} d-flex align-items-center gap-2">
        <i class='{{ $alert['icon'] }} fs-4'></i>
        <div><strong>{{ $alert['title'] }}</strong> {{ $alert['text'] }}</div>
    </div>
@endforeach

@if (! empty($dashboard['tasks']))
    @php
        $openTasks = collect($dashboard['tasks'])->sum('count');
    @endphp
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0 text-uppercase nv-section-title"><i class='bx bx-task me-1'></i>À traiter maintenant</h6>
        <span class="badge {{ $openTasks > 0 ? 'bg-warning-subtle' : 'bg-success-subtle' }}">{{ $openTasks > 0 ? $openTasks.' en attente' : 'Tout est à jour' }}</span>
    </div>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-{{ min(5, count($dashboard['tasks'])) }} g-3 mb-4">
        @foreach ($dashboard['tasks'] as $task)
            <div class="col">
                <a href="{{ route($task['route'], $task['params']) }}" class="nv-task nv-task-{{ $task['color'] }} {{ $task['count'] === 0 ? 'is-empty' : '' }}">
                    <span class="nv-task-icon"><i class='{{ $task['icon'] }}'></i></span>
                    <span class="nv-task-body">
                        <span class="nv-task-count">{{ $task['count'] }}</span>
                        <span class="nv-task-label">{{ $task['label'] }}</span>
                        <span class="nv-task-hint">{{ $task['hint'] }}</span>
                    </span>
                    <i class='bx bx-right-arrow-alt nv-task-go'></i>
                </a>
            </div>
        @endforeach
    </div>
@endif

@foreach ($dashboard['sections'] as $index => $section)
    <h6 class="mb-2 text-uppercase nv-section-title mt-2"><i class='{{ $section['icon'] }} me-1'></i>{{ $section['title'] }}</h6>
    @if (! empty($section['cards']))
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-{{ count($section['cards']) % 3 === 0 ? 3 : (count($section['cards']) >= 4 ? 4 : count($section['cards'])) }}">
            @foreach ($section['cards'] as $card)
                @php
                    $text = $card['darkText'] ? 'text-dark' : 'text-white';
                @endphp
                <div class="col mb-3">
                    <div class="card radius-10 bg-{{ $card['color'] }} bg-gradient h-100 mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div>
                                    <p class="mb-0 {{ $text }}">{{ $card['label'] }}</p>
                                    <h4 class="my-1 {{ $text }}">{{ $card['value'] }}</h4>
                                    @if ($card['sub'])
                                        <p class="mb-0 {{ $text }} small">{{ $card['sub'] }}</p>
                                    @endif
                                </div>
                                <div class="{{ $text }} ms-auto font-35"><i class='{{ $card['icon'] }}'></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Le graphique d'activité suit les chiffres du magasin. --}}
    @if ($section['title'] === 'Magasin' && $dashboard['chart'])
        @include('dashboard._activity-chart', ['chart' => $dashboard['chart']])
    @endif

    @if (! empty($section['tables']))
        <div class="row">
            @foreach ($section['tables'] as $table)
                <div class="col-xl-{{ count($section['tables']) === 1 ? 12 : 6 }}">
                    <div class="card">
                        <div class="card-header card-header-brand">
                            <h6 class="text-white mb-0"><i class='{{ $table['icon'] }} me-2'></i>{{ $table['title'] }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                            <table class="table table-sm dash-table">
                                <thead>
                                    <tr>
                                        @foreach ($table['columns'] as $column)
                                            <th>{{ $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table['rows'] as $row)
                                        <tr>
                                            @foreach ($row as $cell)
                                                <td>
                                                    @if (is_array($cell))
                                                        <span class="badge {{ $cell['class'] }}">{{ $cell['badge'] }}</span>
                                                    @else
                                                        {{ $cell }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endforeach

@if (! empty($dashboard['quick']))
    <div class="card">
        <div class="card-header card-header-brand">
            <h6 class="text-white mb-0"><i class='bx bx-bolt-circle me-2'></i>ACCÈS RAPIDES</h6>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
            @foreach ($dashboard['quick'] as $link)
                <a href="{{ route($link['route'], $link['params']) }}" class="btn btn-outline-primary">
                    <i class='{{ $link['icon'] }} me-1'></i>{{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </div>
@endif

@if (empty($dashboard['sections']) && empty($dashboard['tasks']))
    <div class="card">
        <div class="card-body text-center py-5">
            <i class='bx bx-lock-open-alt fs-1 text-primary'></i>
            <h5 class="mt-2">Bienvenue, {{ auth()->user()->name }}</h5>
            <p class="text-muted mb-0">Aucun module ne vous est encore attribué. Votre administrateur vous donnera les accès nécessaires.</p>
        </div>
    </div>
@endif

@push('styles')
    <style>
        /* Tableaux courts du tableau de bord : retour à la ligne autorisé, toutes les colonnes visibles dans la carte. */
        .dash-table { width: 100% !important; }
        .table-responsive .dash-table th, .table-responsive .dash-table td { white-space: normal; }
    </style>
@endpush

@push('scripts')
    <script>
        $('.dash-table').DataTable({
            pageLength: 5,
            lengthChange: false,
            info: false,
            searching: false,
            ordering: false,
            scrollX: false,
            language: { search: 'Filtrer :', paginate: { previous: '‹', next: '›' }, zeroRecords: 'Rien à afficher', emptyTable: 'Aucune donnée' }
        });
    </script>
@endpush
