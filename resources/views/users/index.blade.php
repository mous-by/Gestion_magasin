@extends('layouts.admin')

@section('title', 'Utilisateurs')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Paramètres</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Utilisateurs</li>
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
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-user-circle me-2'></i>UTILISATEURS</h6>
            @can('create', \App\Models\User::class)
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class='bx bx-plus'></i> Ajouter
                </button>
            @endcan
        </div>
        <div class="card-body">
            @php
                $roleCount = fn (array $names) => $users->filter(fn ($u) => in_array($u->roleName()?->value, $names, true))->count();
                $inactiveCount = $users->where('is_active', false)->count();
            @endphp
            <div class="nv-chips mb-3" id="users-filter">
                <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $users->count() }}</b></button>
                <button type="button" class="nv-chip" data-token="role-magasinier">Magasiniers <b>{{ $roleCount(['magasinier']) }}</b></button>
                <button type="button" class="nv-chip" data-token="role-admin">Administration <b>{{ $roleCount(['superadmin', 'administrateur']) }}</b></button>
                <button type="button" class="nv-chip" data-token="inactif">Inactifs <b>{{ $inactiveCount }}</b></button>
            </div>
            <div class="table-responsive">
                <table class="table" id="users-table">
                    <thead>
                        <tr>
                            <th>UTILISATEUR</th>
                            <th>RÔLE</th>
                            <th>STATUT</th>
                            <th width="15%">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php($roleValue = $user->roleName()?->value)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <div class="small text-muted">{{ $user->phone }}</div>
                                </td>
                                <td>{{ $user->roleName()?->label() ?? '—' }}</td>
                                <td>
                                    <span class="d-none">{{ in_array($roleValue, ['superadmin', 'administrateur'], true) ? 'role-admin' : 'role-'.$roleValue }}{{ $user->is_active ? '' : ' inactif' }}</span>
                                    @if ($user->is_active)
                                        <span class="badge bg-success-subtle">Actif</span>
                                    @else
                                        <span class="badge bg-secondary-subtle">Inactif</span>
                                    @endif
                                    @if ($user->must_change_password)
                                        <span class="badge bg-warning-subtle">Mot de passe temporaire</span>
                                    @endif
                                </td>
                                <td class="d-flex gap-1">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ficheUser-{{ $user->id }}" title="Consulter">
                                        <i class='bx bx-show'></i>
                                    </button>
                                    @can('update', $user)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUser-{{ $user->id }}" title="Modifier">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                    @endcan
                                    @can('resetPassword', $user)
                                        <form method="POST" action="{{ route('users.reset-password', $user) }}" class="js-confirm" data-title="Réinitialiser le mot de passe ?" data-text="{{ $user->name }} devra changer son mot de passe à la prochaine connexion." data-confirm="Réinitialiser">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm" title="Réinitialiser le mot de passe">
                                                <i class='bx bx-key'></i>
                                            </button>
                                        </form>
                                    @endcan
                                    @can('revokeAccess', $user)
                                        <form method="POST" action="{{ route('users.revoke-access', $user) }}" class="js-confirm" data-title="Révoquer l'accès ?" data-text="Les sessions et jetons de {{ $user->name }} seront supprimés." data-confirm="Révoquer">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm" title="Révoquer sessions et jetons">
                                                <i class='bx bx-block'></i>
                                            </button>
                                        </form>
                                    @endcan
                                    @can('delete', $user)
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="js-confirm" data-title="Supprimer cet utilisateur ?" data-text="{{ $user->name }} sera supprimé (récupérable)." data-confirm="Supprimer">
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
        </div>
    </div>

    @foreach ($users as $user)
        @include('users._fiche', ['user' => $user])
    @endforeach
    @can('create', \App\Models\User::class)
        <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('users.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title"><i class='bx bx-user-circle me-2'></i>Nouvel utilisateur</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        @php($createFailed = $errors->any() && ! old('user_id'))
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">Nom</label>
                                <input type="text" class="form-control @if ($createFailed && $errors->has('name')) is-invalid @endif" id="name" name="name" value="{{ $createFailed ? old('name') : '' }}" required>
                                @if ($createFailed && $errors->has('name'))<div class="invalid-feedback">{{ $errors->first('name') }}</div>@endif
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label fw-semibold">Numéro de téléphone</label>
                                <input type="text" class="form-control @if ($createFailed && $errors->has('phone')) is-invalid @endif" id="phone" name="phone" value="{{ $createFailed ? old('phone') : '' }}" placeholder="70 00 00 01" required>
                                @if ($createFailed && $errors->has('phone'))<div class="invalid-feedback">{{ $errors->first('phone') }}</div>@endif
                                <small class="text-muted">Identifiant de connexion. Un mot de passe temporaire sera généré.</small>
                            </div>

                            @if (count($assignableRoles) > 1)
                                <div class="mb-3">
                                    <label for="role" class="form-label fw-semibold">Rôle</label>
                                    <select class="form-select single-select" id="role" name="role" required>
                                        <option value="">— Choisir —</option>
                                        @foreach ($assignableRoles as $role)
                                            <option value="{{ $role->value }}" @selected($createFailed && old('role') === $role->value)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                    @if ($createFailed && $errors->has('role'))<div class="text-danger small mt-1">{{ $errors->first('role') }}</div>@endif
                                </div>
                            @else
                                <input type="hidden" name="role" value="{{ $assignableRoles[0]->value ?? '' }}">
                                <p class="mb-3"><span class="text-muted">Rôle :</span> <strong>{{ $assignableRoles[0]?->label() }}</strong></p>
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

        @if ($createFailed ?? false)
            <script>
                window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('createUserModal')).show());
            </script>
        @endif
    @endcan

    @foreach ($users as $user)
        @can('update', $user)
            @php($editFailed = $errors->any() && old('user_id') == $user->id)
            <div class="modal fade" id="editUser-{{ $user->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('users.update', $user) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class='bx bx-user-circle me-2'></i>Modifier — {{ $user->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label for="name-{{ $user->id }}" class="form-label fw-semibold">Nom</label>
                                    <input type="text" class="form-control @if ($editFailed && $errors->has('name')) is-invalid @endif" id="name-{{ $user->id }}" name="name" value="{{ $editFailed ? old('name') : $user->name }}" required>
                                    @if ($editFailed && $errors->has('name'))<div class="invalid-feedback">{{ $errors->first('name') }}</div>@endif
                                </div>
                                <div class="mb-3">
                                    <label for="phone-{{ $user->id }}" class="form-label fw-semibold">Numéro de téléphone</label>
                                    <input type="text" class="form-control @if ($editFailed && $errors->has('phone')) is-invalid @endif" id="phone-{{ $user->id }}" name="phone" value="{{ $editFailed ? old('phone') : $user->phone }}" required>
                                    @if ($editFailed && $errors->has('phone'))<div class="invalid-feedback">{{ $errors->first('phone') }}</div>@endif
                                </div>

                                @can('activate', $user)
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="active-{{ $user->id }}" name="is_active" value="1" @checked($editFailed ? old('is_active') : $user->is_active)>
                                        <label class="form-check-label" for="active-{{ $user->id }}">Compte actif</label>
                                    </div>
                                    <small class="text-muted">Désactiver coupe immédiatement l'accès de cet utilisateur.</small>
                                @endcan
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if ($editFailed ?? false)
                <script>
                    window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('editUser-{{ $user->id }}')).show());
                </script>
            @endif
        @endcan
    @endforeach

    @if (session('temporary_password'))
        <div class="modal fade" id="temporaryPasswordModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class='bx bx-key me-2'></i>Mot de passe temporaire</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p>Pour <strong>{{ session('temporary_password_for') }}</strong>, à transmettre en main propre. Il ne sera plus affiché ensuite.</p>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="temporary-password-value" value="{{ session('temporary_password') }}" readonly>
                            <button type="button" class="btn btn-primary" id="copy-temporary-password"><i class='bx bx-copy'></i></button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">J'ai noté le mot de passe</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                new bootstrap.Modal(document.getElementById('temporaryPasswordModal')).show();
                document.getElementById('copy-temporary-password').addEventListener('click', () => {
                    navigator.clipboard?.writeText(document.getElementById('temporary-password-value').value);
                });
            });
        </script>
    @endif
@endsection

@push('scripts')
    <script>
        const usersTable = $('#users-table').DataTable({ scrollX: false });

        document.querySelectorAll('#users-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#users-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                usersTable.column(2).search(chip.dataset.token || '', false, true).draw();
            });
        });

        $(document).on('submit', '.js-confirm', function (event) {
            event.preventDefault();
            const form = this;
            Swal.fire({
                icon: 'warning',
                title: $(form).data('title'),
                text: $(form).data('text'),
                showCancelButton: true,
                confirmButtonText: $(form).data('confirm'),
                cancelButtonText: 'Annuler',
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });
    </script>
@endpush
