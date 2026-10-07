@extends('layouts.guest')

@section('title', "Service indisponible")

@section('content')
    <div class="text-center py-3">
        <div class="display-4 fw-bold text-primary">503</div>
        <h5 class="mt-2">Service indisponible</h5>
        <p class="text-muted">La plateforme est en maintenance. Revenez dans quelques instants.</p>
        <a href="{{ url('/') }}" class="btn login-button px-4"><i class="bx bx-home-alt"></i> Retour à l'accueil</a>
    </div>
@endsection
