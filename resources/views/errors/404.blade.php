@extends('layouts.guest')

@section('title', "Page introuvable")

@section('content')
    <div class="text-center py-3">
        <div class="display-4 fw-bold text-primary">404</div>
        <h5 class="mt-2">Page introuvable</h5>
        <p class="text-muted">La page demandée n'existe pas.</p>
        <a href="{{ url('/') }}" class="btn login-button px-4"><i class="bx bx-home-alt"></i> Retour à l'accueil</a>
    </div>
@endsection
