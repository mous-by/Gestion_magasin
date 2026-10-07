@extends('layouts.guest')

@section('title', "Trop de requêtes")

@section('content')
    <div class="text-center py-3">
        <div class="display-4 fw-bold text-primary">429</div>
        <h5 class="mt-2">Trop de requêtes</h5>
        <p class="text-muted">Veuillez patienter quelques instants avant de réessayer.</p>
        <a href="{{ url('/') }}" class="btn login-button px-4"><i class="bx bx-home-alt"></i> Retour à l'accueil</a>
    </div>
@endsection
