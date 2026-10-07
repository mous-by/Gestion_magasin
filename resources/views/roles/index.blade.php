@extends('layouts.admin')

@section('title', 'Rôles')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Paramètres</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rôles</li>
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
            <div class="card">
                <div class="card-header card-header-brand">
                    <h6 class="text-white mb-0"><i class='bx bx-id-card me-2'></i>CATALOGUE DES RÔLES</h6>
                </div>
                <div class="card-body">
                    <table class="table" id="roles-table">
                        <thead>
                            <tr><th>RÔLE</th><th>NIVEAU</th><th>UTILISATEURS</th><th width="10%">DÉTAILS</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="fw-bold">{{ $row['name']->label() }} <span class="text-muted small fw-normal">({{ $row['name']->value }})</span></td>
                                    <td>{{ $row['name']->level() }}</td>
                                    <td>{{ $row['users'] }}</td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#role-{{ $row['name']->value }}" title="Détails">
                                            <i class='bx bx-show'></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @foreach ($rows as $row)
        <div class="modal fade" id="role-{{ $row['name']->value }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class='bx bx-id-card me-2'></i>{{ $row['name']->label() }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if ($row['name'] === \App\Enums\RoleName::Superadmin)
                            <p class="mb-0"><i class="bx bx-shield-quarter text-primary"></i> Accès total : le superadmin contourne l'autorisation (<code>Gate::before</code>), mais jamais la logique métier ni l'audit. Aucune permission n'est attachée.</p>
                        @else
                            <p class="mb-1 fw-semibold">Peut gérer</p>
                            <p>{{ count($row['manages']) ? implode(', ', $row['manages']) : 'Aucun rôle.' }}</p>

                            <p class="mb-1 fw-semibold">Par défaut <span class="badge bg-primary">Manifeste</span></p>
                            <p>@forelse ($row['defaults'] as $name)<span class="badge bg-light text-dark border me-1">{{ $name }}</span>@empty Aucune.@endforelse</p>

                            <p class="mb-1 fw-semibold">Attribuables en exception</p>
                            <p>@forelse ($row['optional'] as $name)<span class="badge bg-light text-dark border me-1">{{ $name }}</span>@empty Aucune.@endforelse</p>

                            @can('permissions.assign')
                                <hr />
                                <form method="POST" action="{{ route('roles.permissions.update', $row['role']) }}">
                                    @csrf
                                    @method('PUT')
                                    <p class="mb-2 fw-semibold">Permissions créées depuis l'interface <span class="badge bg-info">Interface</span></p>
                                    @forelse ($customPermissions as $name)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $name }}" id="{{ $row['name']->value }}-{{ $name }}" @checked(in_array($name, $row['custom'], true))>
                                            <label class="form-check-label" for="{{ $row['name']->value }}-{{ $name }}">{{ $name }}</label>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-2">Aucune permission créée depuis l'interface pour le moment.</p>
                                    @endforelse
                                    @if ($customPermissions->isNotEmpty() && $row['role'])
                                        <button type="submit" class="btn btn-primary btn-sm mt-3">Enregistrer</button>
                                    @endif
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
    <script>
        $('#roles-table').DataTable({ paging: false, info: false, searching: false, ordering: false });
    </script>
@endpush
