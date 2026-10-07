{{-- Écran d'un référentiel simple (catégories, unités, services) au gabarit du thème (CLAUDE.md §7) : liste DataTables
     avec pastilles actif / inactif, création et modification en modale, suppression confirmée.
     Paramètres : title, heading, icon, items, model (classe), routePrefix, idField (champ caché qui distingue
     les modales en cas d'erreur), deleteTitle, newLabel, fields (name, label, required, max, placeholder, type text|switch),
     columns (label, value : closure qui renvoie du HTML déjà échappé). --}}
<div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="breadcrumb-title pe-3">{{ $title }}</div>
    <div class="ps-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 p-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                <li class="breadcrumb-item">Référentiels</li>
                <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
            </ol>
        </nav>
    </div>
</div>
<hr />
@include('partials.referentiels-tabs')

@php
    $activeCount = $items->where('is_active', true)->count();
@endphp
<div class="card">
    <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="mb-0 text-white"><i class='{{ $icon }} me-2'></i>{{ $heading }}</h6>
        @can('create', $model)
            <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#create-{{ $routePrefix }}">
                <i class='bx bx-plus'></i> Ajouter
            </button>
        @endcan
    </div>
    <div class="card-body">
        <div class="nv-chips mb-3" id="{{ $routePrefix }}-filter">
            <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $items->count() }}</b></button>
            <button type="button" class="nv-chip" data-token="statut-actif">Actifs <b>{{ $activeCount }}</b></button>
            <button type="button" class="nv-chip" data-token="statut-inactif">Inactifs <b>{{ $items->count() - $activeCount }}</b></button>
        </div>
        <div class="table-responsive">
            <table class="table" id="{{ $routePrefix }}-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                        <th>STATUT</th>
                        <th width="10%">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            @foreach ($columns as $column)
                                <td class="cell-wrap text-break">{!! $column['value']($item) !!}</td>
                            @endforeach
                            <td>
                                <span class="d-none">{{ $item->is_active ? 'statut-actif' : 'statut-inactif' }}</span>
                                @if ($item->is_active)
                                    <span class="badge bg-success-subtle">Actif</span>
                                @else
                                    <span class="badge bg-secondary-subtle">Inactif</span>
                                @endif
                            </td>
                            <td class="d-flex gap-1">
                                @can('update', $item)
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit-{{ $routePrefix }}-{{ $item->id }}" title="Modifier">
                                        <i class='bx bx-edit'></i>
                                    </button>
                                @endcan
                                @can('delete', $item)
                                    <form method="POST" action="{{ route($routePrefix.'.destroy', $item) }}" class="js-confirm-delete" data-title="{{ $deleteTitle }}" data-name="{{ $item->name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-primary btn-sm" title="Supprimer">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@php
    // Une modale par formulaire : null = création. Les erreurs rouvrent la bonne modale (champ caché $idField).
    $forms = collect([null])->merge($items->filter(fn ($item) => auth()->user()->can('update', $item)));
@endphp
@foreach ($forms as $item)
    @continue($item === null && ! auth()->user()->can('create', $model))
    @php
        $modalId = $item ? "edit-{$routePrefix}-{$item->id}" : "create-{$routePrefix}";
        $failed = $errors->any() && ($item ? old($idField) == $item->id : ! old($idField));
        $sfx = $item ? '-'.$item->id : '';
    @endphp
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ $item ? route($routePrefix.'.update', $item) : route($routePrefix.'.store') }}">
                    @csrf
                    @if ($item)
                        @method('PUT')
                        <input type="hidden" name="{{ $idField }}" value="{{ $item->id }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title"><i class='{{ $icon }} me-2'></i>{{ $item ? 'Modifier — '.$item->name : $newLabel }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body p-4">
                        @foreach ($fields as $field)
                            @php
                                $name = $field['name'];
                            @endphp
                            @if (($field['type'] ?? 'text') === 'switch')
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="{{ $name.$sfx }}" name="{{ $name }}" value="1" @checked($failed ? old($name) : $item?->{$name})>
                                    <label class="form-check-label" for="{{ $name.$sfx }}">{{ $field['label'] }}</label>
                                </div>
                            @else
                                <div class="mb-3">
                                    <label for="{{ $name.$sfx }}" class="form-label fw-semibold">{{ $field['label'] }}@if ($field['required'] ?? false) <span class="text-danger">*</span>@endif</label>
                                    <input type="text" class="form-control @if ($failed && $errors->has($name)) is-invalid @endif" id="{{ $name.$sfx }}" name="{{ $name }}"
                                           value="{{ $failed ? old($name) : $item?->{$name} }}" maxlength="{{ $field['max'] }}" placeholder="{{ $field['placeholder'] ?? '' }}" @required($field['required'] ?? false)>
                                    @if ($failed && $errors->has($name))<div class="invalid-feedback">{{ $errors->first($name) }}</div>@endif
                                </div>
                            @endif
                        @endforeach
                        @if ($item)
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="active{{ $sfx }}" name="is_active" value="1" @checked($failed ? old('is_active') : $item->is_active)>
                                <label class="form-check-label" for="active{{ $sfx }}">Actif</label>
                            </div>
                            <small class="text-muted">Inactif : reste dans l'historique mais n'est plus proposé dans les nouvelles saisies.</small>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if ($failed)
        <script>
            window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('{{ $modalId }}')).show());
        </script>
    @endif
@endforeach

@push('scripts')
    <script>
        const referentielTable = $('#{{ $routePrefix }}-table').DataTable({ scrollX: false });
        const statusColumn = {{ count($columns) }};

        document.querySelectorAll('#{{ $routePrefix }}-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#{{ $routePrefix }}-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                referentielTable.column(statusColumn).search(chip.dataset.token || '', false, true).draw();
            });
        });
    </script>
    @include('partials.confirm-delete')
@endpush
