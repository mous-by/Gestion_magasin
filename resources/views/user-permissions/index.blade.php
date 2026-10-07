@extends('layouts.admin')

@section('title', 'Attribution des permissions')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Paramètres</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Attribution des permissions</li>
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
                    <h6 class="text-white mb-0"><i class='bx bx-user-check me-2'></i>EXCEPTIONS DE PERMISSIONS PAR UTILISATEUR</h6>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                    @endif

                    <form method="GET" action="{{ route('user-permissions.index') }}" class="mb-4">
                        <label for="user" class="form-label fw-semibold">Utilisateur</label>
                        <div class="d-flex gap-2">
                            <select name="user" id="user" class="form-select" onchange="this.form.submit()">
                                <option value="">— Choisir un utilisateur —</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($selected?->id === $user->id)>{{ $user->name }} ({{ $user->roleName()?->label() ?? 'sans rôle' }})</option>
                                @endforeach
                            </select>
                            <noscript><button class="btn btn-primary" type="submit">Afficher</button></noscript>
                        </div>
                        <small class="text-muted">Le superadmin n'apparaît pas : il a déjà tous les accès.</small>
                    </form>

                    @if ($selected)
                        <form method="POST" action="{{ route('users.permissions.update', $selected) }}">
                            @csrf
                            @method('PUT')

                            <h6 class="text-uppercase text-muted border-bottom pb-2 mb-2">Héritées du rôle « {{ $selected->roleName()?->label() }} »</h6>
                            @forelse ($pool['inherited'] as $permission)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" checked disabled id="inh-{{ $permission->id }}">
                                    <label class="form-check-label" for="inh-{{ $permission->id }}">{{ $permission->name }}</label>
                                </div>
                            @empty
                                <p class="text-muted">Aucune permission par défaut pour ce rôle.</p>
                            @endforelse

                            <h6 class="text-uppercase text-muted border-bottom pb-2 mb-2 mt-4">Exceptions attribuables</h6>
                            @forelse ($pool['assignable']->groupBy(fn ($p) => $p->module()) as $module => $permissions)
                                <p class="mb-1 fw-semibold text-uppercase small text-muted">{{ $module }}</p>
                                @foreach ($permissions as $permission)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="perm-{{ $permission->id }}" @checked(in_array($permission->name, $direct, true))>
                                        <label class="form-check-label" for="perm-{{ $permission->id }}">
                                            {{ $permission->name }}
                                            <span class="badge {{ $permission->isCustom() ? 'bg-info' : 'bg-primary' }}">{{ $permission->isCustom() ? 'Interface' : 'Manifeste' }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            @empty
                                <p class="text-muted">Aucune exception attribuable à ce rôle pour le moment.</p>
                            @endforelse

                            @if ($pool['assignable']->isNotEmpty())
                                <button type="submit" class="btn btn-primary mt-3">Enregistrer</button>
                            @endif
                        </form>
                        <p class="text-muted small mt-3 mb-0">Une exception s'ajoute aux permissions du rôle ; une permission héritée du rôle ne peut pas être retirée à un seul utilisateur.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
