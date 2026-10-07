@extends('layouts.guest')

@section('title', "Session expirée")

@section('content')
    <div class="text-center py-3">
        <div class="display-4 fw-bold text-primary">419</div>
        <h5 class="mt-2">Session expirée</h5>
        <p class="text-muted">Votre session a expiré. Rechargez la page et réessayez.</p>
        <a href="{{ url('/') }}" class="btn login-button px-4"><i class="bx bx-home-alt"></i> Retour à l'accueil</a>
    </div>
@endsection
