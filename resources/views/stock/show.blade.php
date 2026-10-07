@extends('layouts.admin')

@section('title', 'Fiche de mouvement — '.$article->reference)

@section('content')
    @php
        $q = fn ($value) => \App\Support\Quantity::format($value);
        $unit = $article->unite?->symbol;
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Stock</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('stock.index') }}">État du stock</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $article->reference }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-history me-2'></i>FICHE DE MOUVEMENT — {{ mb_strtoupper($article->designation) }}</h6>
            <div class="d-flex gap-2">
                @can('stock.regularize')
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#regularize-{{ $article->id }}"><i class='bx bx-adjust'></i> Régulariser</button>
                @endcan
                <a href="{{ route('stock.pdf', ['article' => $article] + $filters) }}" target="_blank" class="btn btn-light btn-sm d-flex align-items-center gap-1"><i class='bx bx-printer'></i> PDF</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="nv-panel h-100"><div class="nv-panel-title">Référence</div><div class="fw-bold">{{ $article->reference }}</div><div class="small text-muted">{{ $article->category?->name }}</div></div></div>
                <div class="col-md-3"><div class="nv-panel h-100"><div class="nv-panel-title">Stock actuel</div><div class="fs-4 fw-bold">{{ $article->stockForDisplay() }} {{ $unit }}</div></div></div>
                <div class="col-md-3"><div class="nv-panel h-100"><div class="nv-panel-title">Seuil minimum</div><div class="fw-bold">{{ $article->thresholdForDisplay() ?? '—' }}</div></div></div>
                <div class="col-md-3"><div class="nv-panel h-100"><div class="nv-panel-title">Emplacement</div><div class="fw-bold">{{ $article->location ?? '—' }}</div></div></div>
            </div>

            <form method="GET" action="{{ route('stock.show', $article) }}" class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label for="du" class="form-label small mb-1">Du</label>
                    <input type="date" class="form-control form-control-sm" id="du" name="du" value="{{ $filters['du'] ?? '' }}">
                </div>
                <div class="col-auto">
                    <label for="au" class="form-label small mb-1">Au</label>
                    <input type="date" class="form-control form-control-sm @error('au') is-invalid @enderror" id="au" name="au" value="{{ $filters['au'] ?? '' }}">
                    @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-filter-alt me-1'></i>Filtrer</button>
                    @if ($filters !== [])
                        <a href="{{ route('stock.show', $article) }}" class="btn btn-outline-secondary btn-sm">Tout l'historique</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm align-middle" id="mouvements-table">
                    <thead>
                        <tr><th>DATE</th><th>RÉFÉRENCE</th><th>OPÉRATION</th><th class="text-end">ENTRÉE</th><th class="text-end">SORTIE</th><th class="text-end">SOLDE</th><th>PAR</th><th>MOTIF / DÉTAIL</th></tr>
                    </thead>
                    <tbody>
                        @if (isset($filters['du']))
                            <tr class="table-light"><td colspan="5" class="fst-italic">Solde au {{ \Illuminate\Support\Carbon::parse($filters['du'])->format('d/m/Y') }}</td><td class="text-end fw-bold">{{ $q($opening) }}</td><td colspan="2"></td></tr>
                        @endif
                        @forelse ($rows as $row)
                            @php
                                $m = $row['mouvement'];
                                $source = $m->source;
                                $detail = $m->reason ?? match (true) {
                                    $source instanceof \App\Models\Entree => $source->fournisseur?->name,
                                    $source instanceof \App\Models\Sortie => $source->service?->name.' — '.$source->requester_name,
                                    default => null,
                                };
                            @endphp
                            <tr>
                                <td>{{ $m->moved_on->format('d/m/Y') }}</td>
                                <td class="fw-semibold">{{ $m->reference }}</td>
                                <td>{{ $m->type->label() }}</td>
                                <td class="text-end text-success">{{ (float) $m->quantity > 0 ? $q($m->quantity) : '—' }}</td>
                                <td class="text-end text-danger">{{ (float) $m->quantity < 0 ? $q(-1 * (float) $m->quantity) : '—' }}</td>
                                <td class="text-end fw-bold">{{ $q($row['balance']) }}</td>
                                <td class="small">{{ $m->user?->displayName() }}</td>
                                <td class="small cell-wrap text-break">{{ $detail }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Aucun mouvement sur la période.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold"><td colspan="3">Totaux de la période</td><td class="text-end text-success">{{ $q($totalIn) }}</td><td class="text-end text-danger">{{ $q($totalOut) }}</td><td class="text-end">{{ $q($closing) }}</td><td colspan="2"></td></tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @can('stock.regularize')
        @include('stock._regularize', ['article' => $article])
    @endcan
@endsection
