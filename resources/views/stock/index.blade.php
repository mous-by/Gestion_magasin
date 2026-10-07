@extends('layouts.admin')

@section('title', 'État du stock')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Stock</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">État du stock</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @php
        $levels = $articles->countBy(fn ($a) => $a->stockLevel());
        $categories = $articles->pluck('category')->filter()->unique('id')->sortBy('name');
    @endphp
    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-transfer-alt me-2'></i>ÉTAT DU STOCK</h6>
            @if (Route::has('rapports.index') && auth()->user()->can('rapports.view'))
                <a href="{{ route('rapports.index') }}" class="btn btn-light btn-sm d-flex align-items-center gap-1"><i class='bx bx-printer'></i> Imprimer un état</a>
            @endif
        </div>
        <div class="card-body">
            <p class="small text-muted"><i class='bx bx-info-circle'></i> Le stock est calculé à partir des entrées, sorties et régularisations validées : il ne se saisit pas (cahier §15).</p>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="nv-chips" id="stock-filter">
                    <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $articles->count() }}</b></button>
                    <button type="button" class="nv-chip" data-token="niveau-normal">Normal <b>{{ $levels['normal'] ?? 0 }}</b></button>
                    <button type="button" class="nv-chip" data-token="niveau-sous-seuil">Sous le seuil <b>{{ $levels['sous-seuil'] ?? 0 }}</b></button>
                    <button type="button" class="nv-chip" data-token="niveau-rupture">En rupture <b>{{ $levels['rupture'] ?? 0 }}</b></button>
                </div>
                <div style="min-width: 220px">
                    <select class="form-select form-select-sm" id="stock-category-filter" aria-label="Filtrer par catégorie">
                        <option value="">Toutes les catégories</option>
                        @foreach ($categories as $category)
                            <option value="cat-{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" id="stock-table">
                    <thead>
                        <tr>
                            <th>ARTICLE</th>
                            <th>CATÉGORIE</th>
                            <th>STOCK</th>
                            <th>SEUIL</th>
                            <th>SITUATION</th>
                            <th>DERNIER MOUVEMENT</th>
                            <th width="10%">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($articles as $article)
                            @php
                                $level = $article->stockLevel();
                                $last = $lastMoves[$article->id] ?? null;
                            @endphp
                            <tr>
                                <td class="cell-wrap text-break">
                                    <div class="fw-semibold">{{ $article->designation }}</div>
                                    <div class="small text-muted">{{ $article->reference }}@unless ($article->is_active) · <span class="text-danger">inactif</span>@endunless</div>
                                </td>
                                <td><span class="d-none">cat-{{ $article->category_id }}</span>{{ $article->category?->name }}</td>
                                <td data-order="{{ (float) $article->stock_quantity }}" class="fw-semibold">{{ $article->stockForDisplay() }} {{ $article->unite?->symbol }}</td>
                                <td>{{ $article->thresholdForDisplay() ?? '—' }}</td>
                                <td>
                                    <span class="d-none">niveau-{{ $level }}</span>
                                    @if ($level === 'rupture')
                                        <span class="badge bg-danger-subtle">Rupture</span>
                                    @elseif ($level === 'sous-seuil')
                                        <span class="badge bg-warning-subtle">Sous le seuil</span>
                                    @else
                                        <span class="badge bg-success-subtle">Normal</span>
                                    @endif
                                </td>
                                <td data-order="{{ $last ?? '0' }}">{{ $last ? \Illuminate\Support\Carbon::parse($last)->format('d/m/Y') : '—' }}</td>
                                <td class="d-flex gap-1">
                                    <a href="{{ route('stock.show', $article) }}" class="btn btn-primary btn-sm" title="Fiche de mouvement"><i class='bx bx-history'></i></a>
                                    @can('stock.regularize')
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#regularize-{{ $article->id }}" title="Régulariser"><i class='bx bx-adjust'></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('stock.regularize')
        @foreach ($articles as $article)
            @include('stock._regularize', ['article' => $article])
        @endforeach
    @endcan
@endsection

@push('scripts')
    <script>
        const stockTable = $('#stock-table').DataTable({ scrollX: false });

        document.querySelectorAll('#stock-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#stock-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                stockTable.column(4).search(chip.dataset.token || '', false, true).draw();
            });
        });
        $('#stock-category-filter').on('change', function () {
            stockTable.column(1).search(this.value ? '\\b' + this.value + '\\b' : '', true, false).draw();
        });
        @if (request('filtre'))
            window.addEventListener('DOMContentLoaded', () => document.querySelector('#stock-filter [data-token="niveau-{{ e(request('filtre')) }}"]')?.click());
        @endif
    </script>
@endpush
