@extends('layouts.admin')

@section('title', 'Système')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Paramètres</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Système</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div class="row">
        <div class="col-12 col-lg-3">
            @include('configuration._menu')
        </div>

        <div class="col-12 col-lg-9">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 text-white"><i class='bx bx-server me-2'></i>ENVIRONNEMENT</h6>
                    @if ($environment['debug'] && $environment['env'] === 'production')
                        <span class="badge bg-danger">Debug actif en production</span>
                    @endif
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th class="w-50 text-muted fw-normal">Application</th><td>{{ $environment['name'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">Environnement</th><td><span class="badge bg-primary">{{ $environment['env'] }}</span></td></tr>
                            <tr><th class="text-muted fw-normal">Mode debug</th><td>{{ $environment['debug'] ? 'Actif' : 'Inactif' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Adresse</th><td class="text-break">{{ $environment['url'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">Fuseau horaire</th><td>{{ $environment['timezone'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">Langue</th><td>{{ $environment['locale'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">PHP</th><td>{{ $environment['php'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">Laravel</th><td>{{ $environment['laravel'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">Dernière synchronisation des permissions</th><td>{{ $environment['permissions_synced_at'] ?? 'Jamais' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 text-white"><i class='bx bx-data me-2'></i>BASE DE DONNÉES</h6>
                    <span class="badge {{ $database['ok'] ? 'bg-success' : 'bg-danger' }}">{{ $database['ok'] ? 'Connectée' : 'Injoignable' }}</span>
                </div>
                <div class="card-body">
                    @if ($database['ok'])
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th class="w-50 text-muted fw-normal">Pilote</th><td>{{ $database['driver'] }}</td></tr>
                                <tr><th class="text-muted fw-normal">Version</th><td>{{ $database['version'] }}</td></tr>
                                <tr><th class="text-muted fw-normal">Base</th><td>{{ $database['name'] }}</td></tr>
                                <tr><th class="text-muted fw-normal">Tables</th><td>{{ $database['tables'] }}</td></tr>
                                <tr><th class="text-muted fw-normal">Migrations appliquées</th><td>{{ $database['migrations'] }}</td></tr>
                            </tbody>
                        </table>
                    @else
                        <p class="text-danger mb-0">La connexion à la base de données a échoué. Consultez les journaux du serveur.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-brand">
                    <h6 class="mb-0 text-white"><i class='bx bx-layer me-2'></i>FILE D'ATTENTE, CACHE ET SESSIONS</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th class="w-50 text-muted fw-normal">File d'attente</th><td>{{ $queue['connection'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">Tâches en attente</th><td>{{ $queue['pending'] ?? 'Non disponible pour ce pilote' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Tâches échouées</th><td><span class="badge {{ $queue['failed_total'] > 0 ? 'bg-danger' : 'bg-success' }}">{{ $queue['failed_total'] }}</span></td></tr>
                            <tr><th class="text-muted fw-normal">Cache</th><td>{{ $queue['cache'] }}</td></tr>
                            <tr><th class="text-muted fw-normal">Sessions</th><td>{{ $queue['session'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-brand">
                    <h6 class="mb-0 text-white"><i class='bx bx-wrench me-2'></i>MAINTENANCE</h6>
                </div>
                <div class="card-body">
                    @can('system.maintain')
                        @foreach ($tasks as $key => $task)
                            <form method="POST" action="{{ route('system.maintain', $key) }}" class="js-maintenance d-flex align-items-center justify-content-between gap-3 {{ $loop->last ? '' : 'mb-3 pb-3 border-bottom' }}" data-label="{{ $task['label'] }}" data-help="{{ $task['help'] }}">
                                @csrf
                                <div>
                                    <div class="fw-semibold">{{ $task['label'] }}</div>
                                    <div class="small text-muted">{{ $task['help'] }}</div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm text-nowrap"><i class='bx bx-play me-1'></i>Lancer</button>
                            </form>
                        @endforeach
                    @else
                        <p class="text-muted mb-0">Vous n'avez pas le droit de lancer les actions de maintenance.</p>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-error-circle me-2'></i>TÂCHES ÉCHOUÉES</h6>
            <span class="badge bg-light text-dark">{{ $queue['failed_total'] }} au total, les {{ count($queue['failed']) }} dernières</span>
        </div>
        <div class="card-body">
            @if (count($queue['failed']) === 0)
                <p class="text-muted text-center mb-0 py-3">Aucune tâche échouée.</p>
            @else
                <table class="table" id="failed-jobs-table">
                    <thead>
                        <tr><th>DATE</th><th>FILE</th><th>CONNEXION</th><th>IDENTIFIANT</th><th>ERREUR</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($queue['failed'] as $job)
                            <tr>
                                <td class="text-nowrap">{{ $job['failed_at'] }}</td>
                                <td>{{ $job['queue'] }}</td>
                                <td>{{ $job['connection'] }}</td>
                                <td><code>{{ Str::limit($job['uuid'], 13, '…') }}</code></td>
                                <td class="cell-wrap text-break">{{ $job['error'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        if (document.getElementById('failed-jobs-table')) {
            $('#failed-jobs-table').DataTable({ paging: false, info: false, searching: false, ordering: false, scrollX: false });
        }

        // Confirmation avant une action de maintenance.
        $(document).on('submit', '.js-maintenance', function (event) {
            event.preventDefault();
            const form = this;
            Swal.fire({
                icon: 'question',
                title: $(form).data('label') + ' ?',
                text: $(form).data('help'),
                showCancelButton: true,
                confirmButtonText: 'Lancer',
                cancelButtonText: 'Annuler',
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });
    </script>
@endpush
