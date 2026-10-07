@extends('layouts.admin')

@section('title', $entree ? 'Modifier un brouillon d\'entrée' : 'Nouvelle entrée')

@section('content')
    @php
        // Lignes à afficher : saisie précédente (retour d'erreur de saisie), sinon lignes du brouillon, sinon une ligne
        // vide. Un échec de validation du stock revient sans saisie à restaurer : on relit alors le brouillon.
        $failed = $errors->any() && old('lines') !== null;
        $lines = $failed ? array_values(old('lines', [])) : ($entree?->details->map(fn ($d) => [
            'article_id' => $d->article_id,
            'expected_qty' => \App\Support\Quantity::format($d->expected_qty),
            'delivered_qty' => \App\Support\Quantity::format($d->delivered_qty),
            'accepted_qty' => \App\Support\Quantity::format($d->accepted_qty),
            'rejection_reason' => $d->rejection_reason?->value,
            'observation' => $d->observation,
        ])->all() ?? []);
        if ($lines === []) {
            $lines = [['article_id' => null]];
        }
        $articles = \App\Models\Article::with('unite')->whereIn('id', collect($lines)->pluck('article_id')->filter())->get()->keyBy('id');
        $val = fn (string $field, $default = null) => $failed ? old($field) : ($entree?->{$field} ?? $default);
    @endphp

    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Entrées</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('entrees.index') }}">Entrées</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $entree ? $entree->displayNumber() : 'Nouvelle entrée' }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1"><i class='bx bx-error-circle me-1'></i>L'entrée n'a pas été enregistrée ou validée :</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $entree ? route('entrees.update', $entree) : route('entrees.store') }}" id="entree-form" autocomplete="off">
        @csrf
        @if ($entree)
            @method('PUT')
        @endif
        <input type="hidden" name="action" id="entree-action" value="draft">

        <div class="card">
            <div class="card-header card-header-brand">
                <h6 class="mb-0 text-white"><i class='bx bx-receipt me-2'></i>LIVRAISON</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="received_on" class="form-label fw-semibold">Date de réception <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('received_on') is-invalid @enderror" id="received_on" name="received_on"
                               value="{{ $failed ? old('received_on') : ($entree?->received_on?->toDateString() ?? now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        @error('received_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label for="fournisseur_id" class="form-label fw-semibold">Fournisseur <span class="text-danger">*</span></label>
                        <select class="form-select single-select" id="fournisseur_id" name="fournisseur_id" required>
                            <option value="">— Choisir —</option>
                            @foreach ($fournisseurs as $fournisseur)
                                <option value="{{ $fournisseur->id }}" @selected((int) $val('fournisseur_id') === $fournisseur->id)>{{ $fournisseur->name }}{{ $fournisseur->is_active ? '' : ' (inactif)' }}</option>
                            @endforeach
                        </select>
                        @error('fournisseur_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @if ($fournisseurs->isEmpty())
                            <small class="text-danger">Aucun fournisseur actif : créez-le d'abord dans <a href="{{ route('fournisseurs.index') }}">Fournisseurs</a>.</small>
                        @endif
                    </div>
                    <div class="col-md-2">
                        <label for="delivery_note_number" class="form-label fw-semibold">N° bordereau</label>
                        <input type="text" class="form-control @error('delivery_note_number') is-invalid @enderror" id="delivery_note_number" name="delivery_note_number" value="{{ $val('delivery_note_number') }}" maxlength="60">
                        @error('delivery_note_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label for="order_reference" class="form-label fw-semibold">Réf. commande</label>
                        <input type="text" class="form-control @error('order_reference') is-invalid @enderror" id="order_reference" name="order_reference" value="{{ $val('order_reference') }}" maxlength="60">
                        @error('order_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="observation" class="form-label fw-semibold">Observation générale</label>
                        <textarea class="form-control @error('observation') is-invalid @enderror" id="observation" name="observation" rows="2" maxlength="2000">{{ $val('observation') }}</textarea>
                        @error('observation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="mb-0 text-white"><i class='bx bx-package me-2'></i>ARTICLES REÇUS</h6>
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" id="add-line" title="Ajouter une ligne (Alt + N)">
                    <i class='bx bx-plus'></i> Ajouter une ligne
                </button>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    <i class='bx bx-info-circle'></i>
                    La quantité prévue est facultative (bon de commande). Seule la quantité <strong>acceptée</strong> entre en stock ;
                    tout écart (manquant, excédent, rejet, article non prévu) s'explique dans l'observation de la ligne.
                </p>
                <div class="table-responsive">
                    <table class="table align-middle" id="lines-table">
                        <thead>
                            <tr>
                                <th style="min-width: 260px">ARTICLE</th>
                                <th>STOCK</th>
                                <th style="width: 100px">PRÉVU</th>
                                <th style="width: 100px">LIVRÉ *</th>
                                <th style="width: 100px">ACCEPTÉ *</th>
                                <th>REJETÉ</th>
                                <th style="min-width: 150px">MOTIF</th>
                                <th style="min-width: 200px">OBSERVATION</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                @include('entrees._line', ['i' => $i, 'line' => $line, 'article' => $articles[(int) ($line['article_id'] ?? 0)] ?? null])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div id="discrepancy-summary" class="alert alert-warning py-2 mb-0 d-none"></div>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
                <a href="{{ route('entrees.index') }}" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-outline-primary" data-action="draft"><i class='bx bx-save me-1'></i>Enregistrer le brouillon</button>
                @can('entrees.validate')
                    <button type="submit" class="btn btn-primary" data-action="validate"><i class='bx bx-check-shield me-1'></i>Enregistrer et valider</button>
                @endcan
            </div>
        </div>
    </form>

    <template id="line-template">
        @include('entrees._line', ['i' => '__I__', 'line' => [], 'article' => null])
    </template>
@endsection

@push('scripts')
    <script>
        (function () {
            const tbody = document.querySelector('#lines-table tbody');
            const searchUrl = @json(route('articles.search'));
            let nextIndex = {{ count($lines) }};

            const num = (input) => {
                const v = (input.value || '').replace(/\s/g, '').replace(',', '.');
                return v === '' ? null : Number(v);
            };
            const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const fmt = (n) => Number.isInteger(n) ? String(n) : String(Math.round(n * 1000) / 1000).replace('.', ',');

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
                    row.querySelector('.line-stock').textContent = d.stock + ' ' + (d.unite || '');
                    row.dataset.decimal = d.decimal ? '1' : '0';
                    row.querySelector('.line-delivered').focus();
                });
            }

            // Recalcule la ligne : rejeté = livré − accepté, motif obligatoire si rejet, écarts signalés (cahier §12).
            function refresh() {
                const rows = [...tbody.querySelectorAll('tr.entree-line')];
                const planned = rows.some((r) => num(r.querySelector('.line-expected')) !== null);
                const issues = [];

                rows.forEach((row) => {
                    const expected = num(row.querySelector('.line-expected'));
                    const delivered = num(row.querySelector('.line-delivered'));
                    const accepted = num(row.querySelector('.line-accepted'));
                    const reason = row.querySelector('.line-reason');
                    const label = $(row).find('.line-article option:selected').text().trim();
                    const tags = [];

                    const rejected = delivered !== null && accepted !== null ? delivered - accepted : null;
                    row.querySelector('.line-rejected').textContent = rejected !== null && rejected > 0 ? fmt(rejected) : '—';
                    reason.disabled = !(rejected > 0);
                    if (!(rejected > 0)) reason.value = '';

                    if (rejected !== null && rejected < 0) tags.push('accepté > livré');
                    if (planned && expected === null && label) tags.push('non prévu');
                    if (expected !== null && delivered !== null && delivered < expected) tags.push('manquant ' + fmt(expected - delivered));
                    if (expected !== null && delivered !== null && delivered > expected) tags.push('excédent ' + fmt(delivered - expected));
                    if (rejected > 0) tags.push('rejeté ' + fmt(rejected));

                    row.querySelector('.line-flags').innerHTML = tags.map((t) => '<span class="badge bg-warning-subtle me-1">' + t + '</span>').join('');
                    row.classList.toggle('table-warning', tags.length > 0);
                    if (tags.length && label) issues.push(label + ' : ' + tags.join(', '));
                });

                const summary = document.getElementById('discrepancy-summary');
                summary.classList.toggle('d-none', issues.length === 0);
                summary.innerHTML = '<i class="bx bx-error me-1"></i><strong>Écarts à documenter :</strong><br>' + issues.map(esc).join('<br>');
                return issues;
            }

            function addLine() {
                const html = document.getElementById('line-template').innerHTML.replaceAll('__I__', nextIndex++);
                tbody.insertAdjacentHTML('beforeend', html);
                const row = tbody.lastElementChild;
                initArticleSelect(row);
                $(row).find('.line-article').select2('open');
            }

            tbody.querySelectorAll('tr.entree-line').forEach(initArticleSelect);
            document.getElementById('add-line').addEventListener('click', addLine);
            document.addEventListener('keydown', (e) => { if (e.altKey && (e.key === 'n' || e.key === 'N')) { e.preventDefault(); addLine(); } });

            tbody.addEventListener('click', (e) => {
                const btn = e.target.closest('.line-remove');
                if (!btn) return;
                if (tbody.querySelectorAll('tr.entree-line').length > 1) btn.closest('tr').remove();
                else { $(btn.closest('tr')).find('.line-article').val(null).trigger('change'); btn.closest('tr').querySelectorAll('input').forEach((i) => i.value = ''); }
                refresh();
            });

            tbody.addEventListener('input', (e) => {
                // Saisie rapide : l'acceptée suit la livrée tant qu'elle n'a pas été modifiée à la main.
                if (e.target.classList.contains('line-delivered')) {
                    const accepted = e.target.closest('tr').querySelector('.line-accepted');
                    if (!accepted.dataset.touched) accepted.value = e.target.value;
                }
                if (e.target.classList.contains('line-accepted')) e.target.dataset.touched = '1';
                refresh();
            });

            // « Valider » : confirmation, avec rappel des écarts et des articles non prévus (cahier §12).
            let confirmed = false;
            document.querySelectorAll('#entree-form [data-action]').forEach((btn) => btn.addEventListener('click', () => {
                document.getElementById('entree-action').value = btn.dataset.action;
            }));
            document.getElementById('entree-form').addEventListener('submit', (e) => {
                if (document.getElementById('entree-action').value !== 'validate' || confirmed) return;
                e.preventDefault();
                const issues = refresh();
                Swal.fire({
                    icon: issues.length ? 'warning' : 'question',
                    title: 'Valider cette entrée ?',
                    html: (issues.length ? '<div class="text-start small mb-2"><strong>Écarts :</strong><br>' + issues.map(esc).join('<br>') + '</div>' : '')
                        + 'Les quantités acceptées seront ajoutées au stock. Une entrée validée ne se modifie plus.',
                    showCancelButton: true, confirmButtonText: 'Valider', cancelButtonText: 'Revenir',
                }).then((r) => { if (r.isConfirmed) { confirmed = true; e.target.submit(); } });
            });

            refresh();
        })();
    </script>
@endpush
