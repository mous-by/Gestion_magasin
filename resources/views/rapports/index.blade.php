@extends('layouts.admin')

@section('title', 'Rapports')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Rapports</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rapports et états</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @if ($errors->any())
        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
    @endif

    @php
        // Un formulaire par rapport (cahier §18). Champs : période (du/au), date unique (au), catégorie, fournisseur, service.
        $reports = [
            ['type' => 'etat-stock', 'icon' => 'bx bx-transfer-alt', 'text' => 'Stock de chaque article à une date (aujourd\'hui si vide), avec seuil et situation.', 'fields' => ['date', 'category']],
            ['type' => 'entrees', 'icon' => 'bx bx-log-in-circle', 'text' => 'Toutes les lignes des entrées validées sur la période : livré, accepté, rejeté.', 'fields' => ['period', 'fournisseur']],
            ['type' => 'sorties', 'icon' => 'bx bx-log-out-circle', 'text' => 'Toutes les lignes des sorties validées sur la période, par service bénéficiaire.', 'fields' => ['period', 'service']],
            ['type' => 'ecarts', 'icon' => 'bx bx-error', 'text' => 'Écarts de livraison documentés : manquants, excédents, rejets, articles non prévus.', 'fields' => ['period', 'fournisseur']],
        ];
    @endphp
    <div class="row row-cols-1 row-cols-lg-2 g-3">
        @foreach ($reports as $report)
            <div class="col">
                <div class="card h-100 mb-0">
                    <div class="card-header card-header-brand">
                        <h6 class="mb-0 text-white"><i class='{{ $report['icon'] }} me-2'></i>{{ mb_strtoupper(\App\Services\Rapports\RapportBuilder::TITLES[$report['type']]) }}</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">{{ $report['text'] }}</p>
                        <form method="GET" action="{{ route('rapports.show', $report['type']) }}" target="_blank" class="row g-2">
                            @if (in_array('period', $report['fields'], true))
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="du-{{ $report['type'] }}">Du</label>
                                    <input type="date" class="form-control form-control-sm" id="du-{{ $report['type'] }}" name="du" value="{{ now()->startOfMonth()->toDateString() }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="au-{{ $report['type'] }}">Au</label>
                                    <input type="date" class="form-control form-control-sm" id="au-{{ $report['type'] }}" name="au" value="{{ now()->toDateString() }}">
                                </div>
                            @endif
                            @if (in_array('date', $report['fields'], true))
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="au-{{ $report['type'] }}">À la date du</label>
                                    <input type="date" class="form-control form-control-sm" id="au-{{ $report['type'] }}" name="au" max="{{ now()->toDateString() }}">
                                </div>
                            @endif
                            @foreach (['category' => ['category_id', 'Catégorie', $categories], 'fournisseur' => ['fournisseur_id', 'Fournisseur', $fournisseurs], 'service' => ['service_id', 'Service', $services]] as $field => [$name, $label, $options])
                                @if (in_array($field, $report['fields'], true))
                                    <div class="col-6">
                                        <label class="form-label small mb-1" for="{{ $name }}-{{ $report['type'] }}">{{ $label }}</label>
                                        <select class="form-select form-select-sm" id="{{ $name }}-{{ $report['type'] }}" name="{{ $name }}">
                                            <option value="">Tous</option>
                                            @foreach ($options as $option)
                                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            @endforeach
                            <div class="col-12 d-flex gap-2 mt-3">
                                <button type="submit" name="format" value="pdf" class="btn btn-primary btn-sm"><i class='bx bx-printer me-1'></i>PDF</button>
                                @can('rapports.export')
                                    <button type="submit" name="format" value="csv" class="btn btn-outline-primary btn-sm"><i class='bx bx-spreadsheet me-1'></i>Excel (CSV)</button>
                                @endcan
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="alert alert-info mt-3 small mb-0">
        <i class='bx bx-info-circle me-1'></i>La <strong>fiche de mouvement d'un article</strong> s'imprime depuis
        @if (Route::has('stock.index') && auth()->user()->can('stock.view'))
            <a href="{{ route('stock.index') }}">l'état du stock</a>
        @else
            l'état du stock
        @endif
        (bouton <i class='bx bx-history'></i> puis « PDF »).
    </div>
@endsection
