@extends('layouts.admin')

@section('title', $sortie ? 'Modifier un brouillon de sortie' : 'Nouvelle sortie')

@section('content')
    @php
        // Lignes : saisie précédente (erreur de saisie), sinon lignes du brouillon, sinon une ligne vide.
        $failed = $errors->any() && old('lines') !== null;
        $lines = $failed ? array_values(old('lines', [])) : ($sortie?->details->map(fn ($d) => [
            'article_id' => $d->article_id,
            'quantity' => \App\Support\Quantity::format($d->quantity),
            'observation' => $d->observation,
        ])->all() ?? []);
        if ($lines === []) {
            $lines = [['article_id' => null]];
        }
        $articles = \App\Models\Article::with('unite')->whereIn('id', collect($lines)->pluck('article_id')->filter())->get()->keyBy('id');
        $val = fn (string $field, $default = null) => $failed ? old($field) : ($sortie?->{$field} ?? $default);
    @endphp

    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Sorties</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sorties.index') }}">Sorties</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $sortie ? $sortie->displayNumber() : 'Nouvelle sortie' }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1"><i class='bx bx-error-circle me-1'></i>La sortie n'a pas été enregistrée ou validée :</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $sortie ? route('sorties.update', $sortie) : route('sorties.store') }}" id="sortie-form" autocomplete="off">
        @csrf
        @if ($sortie)
            @method('PUT')
        @endif
        <input type="hidden" name="action" id="sortie-action" value="draft">

        <div class="card">
            <div class="card-header card-header-brand">
                <h6 class="mb-0 text-white"><i class='bx bx-user-voice me-2'></i>DEMANDE</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label for="issued_on" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('issued_on') is-invalid @enderror" id="issued_on" name="issued_on"
                               value="{{ $failed ? old('issued_on') : ($sortie?->issued_on?->toDateString() ?? now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        @error('issued_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="service_id" class="form-label fw-semibold">Service bénéficiaire <span class="text-danger">*</span></label>
                        <select class="form-select single-select" id="service_id" name="service_id" required>
                            <option value="">— Choisir —</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected((int) $val('service_id') === $service->id)>{{ $service->name }}{{ $service->is_active ? '' : ' (inactif)' }}</option>
                            @endforeach
                        </select>
                        @error('service_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @if ($services->isEmpty())
                            <small class="text-danger">Aucun service actif : créez-le d'abord dans <a href="{{ route('services.index') }}">Services bénéficiaires</a>.</small>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <label for="requester_name" class="form-label fw-semibold">Demandeur <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('requester_name') is-invalid @enderror" id="requester_name" name="requester_name" value="{{ $val('requester_name') }}" maxlength="150" required>
                        @error('requester_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="paper_reference" class="form-label fw-semibold">Réf. document papier</label>
                        <input type="text" class="form-control @error('paper_reference') is-invalid @enderror" id="paper_reference" name="paper_reference" value="{{ $val('paper_reference') }}" maxlength="60">
                        @error('paper_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="reason" class="form-label fw-semibold">Motif <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" value="{{ $val('reason') }}" maxlength="255" placeholder="Remplacement des réglettes de l'amphi A" required>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="observation" class="form-label fw-semibold">Observation</label>
                        <input type="text" class="form-control @error('observation') is-invalid @enderror" id="observation" name="observation" value="{{ $val('observation') }}" maxlength="2000">
                        @error('observation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="mb-0 text-white"><i class='bx bx-package me-2'></i>ARTICLES ET QUANTITÉS</h6>
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" id="add-line" title="Ajouter une ligne (Alt + N)">
                    <i class='bx bx-plus'></i> Ajouter une ligne
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle" id="lines-table">
                        <thead>
                            <tr>
                                <th style="min-width: 280px">ARTICLE</th>
                                <th>STOCK DISPONIBLE</th>
                                <th style="width: 120px">SORTIE *</th>
                                <th>STOCK APRÈS</th>
                                <th style="min-width: 200px">OBSERVATION</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                @include('sorties._line', ['i' => $i, 'line' => $line, 'article' => $articles[(int) ($line['article_id'] ?? 0)] ?? null])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div id="stock-alert" class="alert alert-danger py-2 mb-0 d-none"></div>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
                <a href="{{ route('sorties.index') }}" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-outline-primary" data-action="draft"><i class='bx bx-save me-1'></i>Enregistrer le brouillon</button>
                @can('sorties.validate')
                    <button type="submit" class="btn btn-primary" data-action="validate" id="validate-button"><i class='bx bx-check-shield me-1'></i>Valider la sortie</button>
                @endcan
            </div>
        </div>
    </form>

    <template id="line-template">
        @include('sorties._line', ['i' => '__I__', 'line' => [], 'article' => null])
    </template>
@endsection

@push('scripts')
    <script>
        (function () {
            const tbody = document.querySelector('#lines-table tbody');
            const searchUrl = @json(route('articles.search'));
            let nextIndex = {{ count($lines) }};

            const num = (v) => { v = (v || '').replace(/\s/g, '').replace(',', '.'); return v === '' ? null : Number(v); };
            const fmt = (n) => Number.isInteger(n) ? String(n) : String(Math.round(n * 1000) / 1000).replace('.', ',');
            const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            function initArticleSelect(row) {
                const select = $(row).find('.line-article');
                select.select2({
                    theme: 'bootstrap4',
                    placeholder: 'Référence ou désignation…',
                    minimumInputLength: 0,
                    ajax: { url: searchUrl, dataType: 'json', delay: 250, data: (p) => ({ q: p.term || '' }), processResults: (d) => ({ results: d.results }), cache: true },
                });
                select.on('select2:select', (e) => {
                    const d = e.params.data;
                    row.dataset.stock = num(d.stock);
                    row.dataset.unite = d.unite || '';
                    row.querySelector('.line-stock').textContent = d.stock + ' ' + (d.unite || '');
                    refresh();
                    row.querySelector('.line-quantity').focus();
                });
            }

            // Stock disponible visible pendant la saisie et blocage d'une quantité impossible (cahier §13.1, RG04).
            function refresh() {
                const over = [];
                tbody.querySelectorAll('tr.sortie-line').forEach((row) => {
                    const stock = row.dataset.stock === '' ? null : Number(row.dataset.stock);
                    const qty = num(row.querySelector('.line-quantity').value);
                    const after = stock !== null && qty !== null ? stock - qty : null;
                    const cell = row.querySelector('.line-after');
                    cell.textContent = after === null ? '—' : fmt(after) + ' ' + row.dataset.unite;
                    cell.classList.toggle('text-danger', after !== null && after < 0);
                    cell.classList.toggle('fw-bold', after !== null && after < 0);
                    row.classList.toggle('table-danger', after !== null && after < 0);
                    if (after !== null && after < 0) {
                        over.push($(row).find('.line-article option:selected').text().trim() + ' : disponible ' + fmt(stock) + ', demandé ' + fmt(qty));
                    }
                });
                const alert = document.getElementById('stock-alert');
                alert.classList.toggle('d-none', over.length === 0);
                alert.innerHTML = '<i class="bx bx-block me-1"></i><strong>Stock insuffisant — la sortie ne peut pas être validée :</strong><br>' + over.map(esc).join('<br>');
                const validate = document.getElementById('validate-button');
                if (validate) validate.disabled = over.length > 0;
                return over;
            }

            function addLine() {
                tbody.insertAdjacentHTML('beforeend', document.getElementById('line-template').innerHTML.replaceAll('__I__', nextIndex++));
                const row = tbody.lastElementChild;
                initArticleSelect(row);
                $(row).find('.line-article').select2('open');
            }

            tbody.querySelectorAll('tr.sortie-line').forEach(initArticleSelect);
            document.getElementById('add-line').addEventListener('click', addLine);
            document.addEventListener('keydown', (e) => { if (e.altKey && (e.key === 'n' || e.key === 'N')) { e.preventDefault(); addLine(); } });
            tbody.addEventListener('input', refresh);
            tbody.addEventListener('click', (e) => {
                const btn = e.target.closest('.line-remove');
                if (!btn) return;
                if (tbody.querySelectorAll('tr.sortie-line').length > 1) btn.closest('tr').remove();
                else { const row = btn.closest('tr'); $(row).find('.line-article').val(null).trigger('change'); row.querySelectorAll('input').forEach((i) => i.value = ''); row.dataset.stock = ''; row.querySelector('.line-stock').textContent = '—'; }
                refresh();
            });

            let confirmed = false;
            document.querySelectorAll('#sortie-form [data-action]').forEach((btn) => btn.addEventListener('click', () => {
                document.getElementById('sortie-action').value = btn.dataset.action;
            }));
            document.getElementById('sortie-form').addEventListener('submit', (e) => {
                if (document.getElementById('sortie-action').value !== 'validate' || confirmed) return;
                e.preventDefault();
                if (refresh().length) return;
                Swal.fire({
                    icon: 'question', title: 'Valider cette sortie ?',
                    text: 'Les quantités seront retirées du stock et le bon de sortie sera prêt à imprimer. Une sortie validée ne se modifie plus.',
                    showCancelButton: true, confirmButtonText: 'Valider', cancelButtonText: 'Revenir',
                }).then((r) => { if (r.isConfirmed) { confirmed = true; e.target.submit(); } });
            });

            refresh();
        })();
    </script>
@endpush
