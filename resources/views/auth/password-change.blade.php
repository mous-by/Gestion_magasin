@extends('layouts.guest')

@section('title', 'Changer le mot de passe')

@section('content')
    @if ($forced)
        <div class="alert alert-warning py-2 d-flex align-items-start gap-2">
            <i class="bx bx-error fs-5"></i>
            <div>Votre mot de passe est <strong>temporaire</strong> : choisissez-en un nouveau pour continuer.</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="form-divider"><span>Nouveau mot de passe</span></div>

    <form method="POST" action="{{ route('password.change.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="current_password" class="form-label">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Nouveau mot de passe</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
            <div class="form-text">8 caractères au moins, avec des lettres et des chiffres.</div>
        </div>
        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirmer le nouveau mot de passe</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn login-button"><i class="bx bx-check-circle"></i> Enregistrer</button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
        @csrf
        <button type="submit" class="btn btn-link text-muted btn-sm">Se déconnecter</button>
    </form>
@endsection
