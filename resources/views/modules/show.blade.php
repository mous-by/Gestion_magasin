@extends('layouts.admin')

@section('title', $module['label'])

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Modules à venir</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $module['label'] }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header card-header-brand d-flex align-items-center gap-2">
                    <h6 class="mb-0 text-white"><i class="{{ $module['icon'] }} me-2"></i>{{ mb_strtoupper($module['label']) }}</h6>
                    <span class="badge bg-warning text-dark ms-auto">À venir</span>
                </div>
                <div class="card-body">
                    <p class="mb-3">{{ $module['summary'] }}</p>

                    <h6 class="text-uppercase text-muted border-bottom pb-2 mb-2">Dans le cahier des charges</h6>
                    <p class="mb-4">{{ $module['cahier'] }}</p>

                    <h6 class="text-uppercase text-muted border-bottom pb-2 mb-2">Points à valider avec le client avant de commencer</h6>
                    @forelse ($module['open'] as $question)
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <i class="bx bx-help-circle text-warning fs-5"></i>
                            <div>{{ $question }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Aucun point bloquant recensé.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bx bx-wrench fs-4 text-primary"></i>
                        <h6 class="mb-0">État d'avancement</h6>
                    </div>
                    <p class="mb-2">Ce module n'est pas encore implémenté : cette fiche sert à repérer ce qui reste à construire.</p>
                    <p class="text-muted small mb-0">Il s'ajoutera en déposant son manifeste <code>config/modules/{{ $key }}.php</code>, sans modifier le socle : cette entrée disparaîtra alors de la liste.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
