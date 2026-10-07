@extends('layouts.admin')

@section('title', 'Profil')

@section('content')
    @php
        // L'onglet actif suit l'action qui vient d'avoir lieu ; en cas d'erreur, celui du formulaire concerné.
        $activeTab = $errors->hasAny(['current_password', 'password']) ? 'password'
            : ($errors->hasAny(['name', 'phone', 'phone_password']) ? 'info' : (session('tab') ?? request('tab', 'info')));
        $activeTab = in_array($activeTab, ['info', 'password'], true) ? $activeTab : 'info';
    @endphp

    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Profil</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mon compte</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div class="card">
                <div class="card-header card-header-brand">
                    <h6 class="text-white mb-0"><i class='bx bx-user me-2'></i>MON PROFIL</h6>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" role="tablist" id="profile-tabs" data-active="{{ $activeTab }}">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link {{ $activeTab === 'info' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-info" role="tab" aria-selected="{{ $activeTab === 'info' ? 'true' : 'false' }}">
                                <i class='bx bx-id-card me-1'></i>Mes informations
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link {{ $activeTab === 'password' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-password" role="tab" aria-selected="{{ $activeTab === 'password' ? 'true' : 'false' }}">
                                <i class='bx bx-lock-alt me-1'></i>Mot de passe
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content pt-4">
                        {{-- Onglet 1 : informations personnelles --}}
                        <div class="tab-pane fade {{ $activeTab === 'info' ? 'show active' : '' }}" id="tab-info" role="tabpanel">
                            @if ($errors->hasAny(['name', 'phone', 'phone_password']))
                                <div class="alert alert-danger py-2">
                                    @foreach (collect(['name', 'phone', 'phone_password'])->flatMap(fn ($field) => $errors->get($field)) as $message)
                                        <div>{{ $message }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <form method="POST" action="{{ route('profile.update') }}" id="profile-info-form" data-original-phone="{{ $user->phone }}">
                                @csrf
                                @method('PUT')

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label for="name" class="form-label fw-semibold">Nom complet</label>
                                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="150" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="phone" class="form-label fw-semibold">Numéro de téléphone</label>
                                        <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror" inputmode="tel" required>
                                        <div class="form-text">C'est votre <strong>identifiant de connexion</strong>.</div>
                                    </div>
                                    <div class="col-12 {{ $errors->has('phone_password') ? '' : 'd-none' }}" id="phone-confirm">
                                        <div class="alert alert-warning py-2 mb-2"><i class="bx bx-error me-1"></i>Vous changez votre identifiant de connexion : confirmez avec votre mot de passe actuel.</div>
                                        <label for="phone_password" class="form-label fw-semibold">Mot de passe actuel</label>
                                        <input type="password" id="phone_password" name="phone_password" class="form-control @error('phone_password') is-invalid @enderror" autocomplete="current-password">
                                    </div>
                                </div>

                                <hr class="my-4" />
                                <p class="text-uppercase text-muted small fw-bold mb-2">Non modifiable ici</p>
                                <table class="table table-sm mb-4">
                                    <tbody>
                                        <tr><th class="text-muted" width="35%">Rôle</th><td>{{ $user->roleName()?->label() ?? '—' }}</td></tr>
                                        <tr><th class="text-muted">Dernière connexion</th><td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                                        <tr><th class="text-muted">Compte créé le</th><td>{{ $user->created_at?->format('d/m/Y') ?? '—' }}</td></tr>
                                    </tbody>
                                </table>
                                <p class="text-muted small">Le rôle est attribué par votre administrateur.</p>

                                <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Enregistrer les modifications</button>
                            </form>
                        </div>

                        {{-- Onglet 2 : mot de passe --}}
                        <div class="tab-pane fade {{ $activeTab === 'password' ? 'show active' : '' }}" id="tab-password" role="tabpanel">
                            @if ($errors->hasAny(['current_password', 'password']))
                                <div class="alert alert-danger py-2">
                                    @foreach (collect(['current_password', 'password'])->flatMap(fn ($field) => $errors->get($field)) as $message)
                                        <div>{{ $message }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <form method="POST" action="{{ route('profile.password') }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-12 col-md-4">
                                        <label for="current_password" class="form-label fw-semibold">Mot de passe actuel</label>
                                        <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="password" class="form-label fw-semibold">Nouveau mot de passe</label>
                                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                                        <small class="text-muted">8 caractères au moins, avec des lettres et des chiffres.</small>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="password_confirmation" class="form-label fw-semibold">Confirmer le nouveau mot de passe</label>
                                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-4"><i class="bx bx-lock-alt me-1"></i>Changer le mot de passe</button>
                                <p class="text-muted small mt-2 mb-0">Vos autres sessions et appareils seront déconnectés.</p>
                            </form>
                        </div>
                    </div>
                </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Le mot de passe n'est demandé que si le numéro (identifiant de connexion) est réellement modifié.
        (function () {
            const form = document.getElementById('profile-info-form');
            const phone = document.getElementById('phone');
            const confirmBox = document.getElementById('phone-confirm');
            const digits = (value) => value.replace(/\D/g, '').replace(/^(00)?223/, '');
            const original = digits(form.dataset.originalPhone);

            phone.addEventListener('input', function () {
                confirmBox.classList.toggle('d-none', digits(phone.value) === original);
            });
        })();
    </script>
@endpush
