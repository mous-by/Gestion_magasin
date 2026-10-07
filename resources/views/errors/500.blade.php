@extends('layouts.guest')

@section('title', "Erreur du serveur")

@section('content')
    <div class="text-center py-3">
        <div class="display-4 fw-bold text-primary">500</div>
        <h5 class="mt-2">Erreur du serveur</h5>
        <p class="text-muted">Une erreur est survenue. Elle a été enregistrée.</p>
        <a href="{{ url('/') }}" class="btn login-button px-4"><i class="bx bx-home-alt"></i> Retour à l'accueil</a>
    </div>
@endsection
