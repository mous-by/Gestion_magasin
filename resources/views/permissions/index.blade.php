@extends('layouts.admin')

@section('title', 'Permissions')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Paramètres</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Permissions</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div class="row">
        <div class="col-12 col-lg-3">
            @include('configuration._menu')

            <div class="card">
                <div class="card-body small">
                    <p class="mb-2"><span class="badge bg-primary">Manifeste</span> Déclarée dans un module (<code>config/modules</code>), synchronisée par <code>permissions:sync</code>. Non modifiable ici.</p>
                    <p class="mb-2"><span class="badge bg-info">Interface</span> Créée ici par le superadmin. Jamais supprimée ni écrasée par la synchronisation.</p>
                    <p class="mb-0"><span class="badge bg-danger">Réservée</span> Jamais attribuable à un autre que le superadmin.</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-9">
            <div class="card">
                <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 text-white"><i class='bx bx-shield-alt-2 me-2'></i>RÉFÉRENTIEL DES PERMISSIONS</h6>
                    @can('permissions.create')
                        <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addPermissionModal">
                            <i class='bx bx-plus'></i> Ajouter
                        </button>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="position-relative">
                            <input type="text" id="permission-search" class="form-control ps-5" value="{{ $search }}" placeholder="Rechercher une permission...">
                            <i class='bx bx-search position-absolute top-50 translate-middle-y' style="left: .75rem; color: #94a3b8;"></i>
                        </div>
                    </div>
                    <div id="permission-groups">
                        @include('permissions._groups')
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('permissions.create')
        <div class="modal fade" id="addPermissionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('permissions.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title"><i class='bx bx-shield-alt-2 me-2'></i>Nouvelle permission</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">Nom de la permission</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Ex : rapports.export" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Format module.action, en minuscules (chiffres et tirets bas admis). Les modules system, roles, permissions et audit sont réservés.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($errors->has('name'))
            <script>
                window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('addPermissionModal')).show());
            </script>
        @endif
    @endcan
@endsection

@push('scripts')
    <script>
        let searchTimer = null;

        $('#permission-search').on('input', function () {
            const term = $(this).val();

            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                $.ajax({
                    url: '{{ route('permissions.index') }}',
                    method: 'GET',
                    data: { search: term },
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function (html) { $('#permission-groups').html(html); },
                });
            }, 300);
        });

        // Confirmation avant suppression d'une permission créée depuis l'interface.
        $(document).on('submit', '.js-delete-permission', function (event) {
            event.preventDefault();
            const form = this;
            Swal.fire({
                icon: 'warning',
                title: 'Supprimer cette permission ?',
                text: $(form).data('name') + ' sera retirée des rôles et des utilisateurs qui l\'ont.',
                showCancelButton: true,
                confirmButtonText: 'Supprimer',
                cancelButtonText: 'Annuler',
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });
    </script>
@endpush
