@extends('layouts.guest')

@section('title', "Accès refusé")

@section('content')
    <div class="text-center py-3">
        <div class="display-4 fw-bold text-primary">403</div>
        <h5 class="mt-2">Accès refusé</h5>
        <p class="text-muted">Vous n'avez pas la permission d'accéder à cette page.</p>
        <a href="{{ url('/') }}" class="btn login-button px-4"><i class="bx bx-home-alt"></i> Retour à l'accueil</a>
    </div>
@endsection
