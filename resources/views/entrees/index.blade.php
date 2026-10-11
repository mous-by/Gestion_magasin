@extends('layouts.admin')

@section('title', 'Entrées')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Entrées</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Entrées / réceptions</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @php
        $count = fn (\App\Enums\DocumentStatus $status) => $entrees->where('status', $status)->count();
        $withGap = $entrees->filter(fn ($e) => ! $e->isDraft() && $e->hasDiscrepancy())->count();
        $pendingGap = $entrees->filter(fn ($e) => $e->hasPendingDiscrepancy())->count();
    @endphp
    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-log-in-circle me-2'></i>ENTRÉES / RÉCEPTIONS</h6>
            @can('create', \App\Models\Entree::class)
                <a href="{{ route('entrees.create') }}" class="btn btn-light btn-sm d-flex align-items-center gap-1">
                    <i class='bx bx-plus'></i> Nouvelle entrée
                </a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('entrees.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label for="du" class="form-label small mb-1">Du</label>
                    <input type="date" class="form-control form-control-sm @error('du') is-invalid @enderror" id="du" name="du" value="{{ $filters['du'] ?? '' }}">
                </div>
                <div class="col-auto">
                    <label for="au" class="form-label small mb-1">Au</label>
                    <input type="date" class="form-control form-control-sm @error('au') is-invalid @enderror" id="au" name="au" value="{{ $filters['au'] ?? '' }}">
                    @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-filter-alt me-1'></i>Filtrer</button>
                    @if (($filters['du'] ?? null) || ($filters['au'] ?? null))
                        <a href="{{ route('entrees.index') }}" class="btn btn-outline-secondary btn-sm">Toute la période</a>
                    @endif
                </div>
            </form>

            <div class="nv-chips mb-3" id="entrees-filter">
                <button type="button" class="nv-chip active" data-token="">Toutes <b>{{ $entrees->count() }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-brouillon">Brouillons <b>{{ $count(\App\Enums\DocumentStatus::Brouillon) }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-validee">Validées <b>{{ $count(\App\Enums\DocumentStatus::Validee) }}</b></button>
                <button type="button" class="nv-chip" data-token="avec-ecart">Avec écart <b>{{ $withGap }}</b></button>
                <button type="button" class="nv-chip" data-token="ecart-a-traiter">Écart à traiter <b>{{ $pendingGap }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-annulee">Annulées <b>{{ $count(\App\Enums\DocumentStatus::Annulee) }}</b></button>
            </div>
            <div class="table-responsive">
                <table class="table" id="entrees-table">
                    <thead>
                        <tr>
                            <th>ENTRÉE</th>
                            <th>FOURNISSEUR</th>
                            <th>ARTICLES</th>
                            <th>STATUT</th>
                            <th width="16%">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entrees as $entree)
                            @php
                                $gap = $entree->hasDiscrepancy();
                            @endphp
                            <tr>
                                <td data-order="{{ $entree->received_on->format('Ymd') }}{{ str_pad($entree->id, 8, '0', STR_PAD_LEFT) }}">
                                    <div class="fw-semibold">{{ $entree->displayNumber() }}</div>
                                    <div class="small text-muted">{{ $entree->received_on->format('d/m/Y') }}</div>
                                </td>
                                <td class="cell-wrap text-break">
                                    <div>{{ $entree->fournisseur?->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ $entree->delivery_note_number ? 'Bordereau '.$entree->delivery_note_number : 'Sans bordereau' }}</div>
                                </td>
                                <td class="cell-wrap">
                                    <div>{{ $entree->details->count() }} article(s)</div>
                                    <div class="small text-muted text-break">{{ $entree->details->take(3)->map(fn ($d) => $d->article?->designation)->implode(', ') }}{{ $entree->details->count() > 3 ? '…' : '' }}</div>
                                </td>
                                <td>
                                    <span class="d-none">statut-{{ $entree->status->value }}{{ $gap && ! $entree->isDraft() ? ' avec-ecart' : '' }}{{ $entree->hasPendingDiscrepancy() ? ' ecart-a-traiter' : '' }}</span>
                                    <span class="badge {{ $entree->status->badgeClass() }}">{{ $entree->status->label() }}</span>
                                    @if ($entree->hasPendingDiscrepancy())
                                        <span class="badge bg-danger-subtle">Écart à traiter</span>
                                    @elseif ($gap)
                                        <span class="badge bg-warning-subtle">{{ $entree->discrepancy_resolved_at ? 'Écart traité' : 'Écart' }}</span>
                                    @endif
                                </td>
                                <td class="d-flex gap-1 flex-wrap">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ficheEntree-{{ $entree->id }}" title="Détail de l'entrée">
                                        <i class='bx bx-show'></i>
                                    </button>
                                    @if ($entree->isDraft())
                                        @can('update', $entree)
                                            <a href="{{ route('entrees.edit', $entree) }}" class="btn btn-primary btn-sm" title="Modifier le brouillon"><i class='bx bx-edit'></i></a>
                                        @endcan
                                        @can('validate', $entree)
                                            <form method="POST" action="{{ route('entrees.validate', $entree) }}" class="js-confirm-validate" data-name="{{ $entree->displayNumber() }}" data-gap="{{ $gap ? 1 : 0 }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm" title="Valider (mise à jour du stock)"><i class='bx bx-check-shield'></i></button>
                                            </form>
                                        @endcan
                                        @can('delete', $entree)
                                            <form method="POST" action="{{ route('entrees.destroy', $entree) }}" class="js-confirm-delete" data-title="Supprimer ce brouillon ?" data-name="{{ $entree->displayNumber() }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-primary btn-sm" title="Supprimer le brouillon"><i class='bx bx-trash'></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                    <a href="{{ route('entrees.pdf', $entree) }}" target="_blank" class="btn btn-primary btn-sm" title="Bon d'entrée du magasin (PDF)"><i class='bx bx-printer'></i></a>
                                    @if ($entree->hasPendingDiscrepancy())
                                        @can('resolve', $entree)
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#resolveEntree-{{ $entree->id }}" title="Traiter l'écart"><i class='bx bx-check-double'></i></button>
                                        @endcan
                                    @endif
                                    @if ($entree->isValidated())
                                        @can('cancel', $entree)
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cancelEntree-{{ $entree->id }}" title="Annuler l'entrée"><i class='bx bx-undo'></i></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($entrees as $entree)
        @include('entrees._fiche', ['entree' => $entree])

        @if ($entree->hasPendingDiscrepancy())
            @can('resolve', $entree)
                @php
                    $resolveFailed = $errors->has('resolution') && (int) old('entree_id') === $entree->id;
                @endphp
                <div class="modal fade" id="resolveEntree-{{ $entree->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('entrees.resolve', $entree) }}">
                                @csrf
                                <input type="hidden" name="entree_id" value="{{ $entree->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class='bx bx-check-double me-2'></i>Traiter l'écart — {{ $entree->number }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <ul class="small mb-3">
                                        @foreach ($entree->details as $detail)
                                            @php
                                                $labels = $detail->discrepancies($entree->isPlanned());
                                            @endphp
                                            @if ($labels !== [])
                                                <li><strong>{{ $detail->article?->designation }}</strong> : {{ implode(', ', $labels) }}{{ $detail->observation ? ' — '.$detail->observation : '' }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                    <label for="resolution-{{ $entree->id }}" class="form-label fw-semibold">Suite donnée <span class="text-danger">*</span></label>
                                    <textarea class="form-control @if ($resolveFailed) is-invalid @endif" id="resolution-{{ $entree->id }}" name="resolution" rows="3" maxlength="1000" placeholder="Complément livré le … (nouvelle entrée), avoir obtenu, retour au fournisseur, perte acceptée…" required>{{ $resolveFailed ? old('resolution') : '' }}</textarea>
                                    @if ($resolveFailed)<div class="invalid-feedback">{{ $errors->first('resolution') }}</div>@endif
                                    <small class="text-muted">Le stock n'est pas modifié : une livraison complémentaire s'enregistre comme une nouvelle entrée.</small>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @if ($resolveFailed)
                    <script>
                        window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('resolveEntree-{{ $entree->id }}')).show());
                    </script>
                @endif
            @endcan
        @endif

        @if ($entree->isValidated())
            @can('cancel', $entree)
                @php
                    $cancelFailed = $errors->has('reason') && (int) old('entree_id') === $entree->id;
                @endphp
                <div class="modal fade" id="cancelEntree-{{ $entree->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('entrees.cancel', $entree) }}">
                                @csrf
                                <input type="hidden" name="entree_id" value="{{ $entree->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class='bx bx-undo me-2'></i>Annuler l'entrée {{ $entree->number }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <p class="small">Les quantités acceptées seront retirées du stock par un mouvement d'annulation. L'entrée reste dans l'historique, marquée « Annulée ».</p>
                                    <label for="reason-{{ $entree->id }}" class="form-label fw-semibold">Motif de l'annulation <span class="text-danger">*</span></label>
                                    <textarea class="form-control @if ($cancelFailed) is-invalid @endif" id="reason-{{ $entree->id }}" name="reason" rows="3" maxlength="255" required>{{ $cancelFailed ? old('reason') : '' }}</textarea>
                                    @if ($cancelFailed)<div class="invalid-feedback">{{ $errors->first('reason') }}</div>@endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                    <button type="submit" class="btn btn-danger">Annuler l'entrée</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @if ($cancelFailed)
                    <script>
                        window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('cancelEntree-{{ $entree->id }}')).show());
                    </script>
                @endif
            @endcan
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>
        const entreesTable = $('#entrees-table').DataTable({ scrollX: false, order: [[0, 'desc']] });

        document.querySelectorAll('#entrees-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#entrees-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                entreesTable.column(3).search(chip.dataset.token || '', false, true).draw();
            });
        });

        $(document).on('submit', '.js-confirm-validate', function (event) {
            event.preventDefault();
            const form = this;
            Swal.fire({
                icon: $(form).data('gap') ? 'warning' : 'question',
                title: 'Valider ' + $(form).data('name') + ' ?',
                text: ($(form).data('gap') ? 'Cette entrée comporte des écarts (voir le détail). ' : '') + 'Les quantités acceptées seront ajoutées au stock ; l\'entrée ne sera plus modifiable.',
                showCancelButton: true, confirmButtonText: 'Valider', cancelButtonText: 'Revenir',
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });

        @if (request('filtre') === 'ecart-a-traiter')
            window.addEventListener('DOMContentLoaded', () => document.querySelector('#entrees-filter [data-token="ecart-a-traiter"]')?.click());
        @endif
        @if (session('print'))
            // Après validation : proposer l'impression du bon d'entrée du magasin.
            window.addEventListener('DOMContentLoaded', () => setTimeout(() => Swal.fire({
                icon: 'success', title: 'Entrée validée', text: 'Imprimer le bon d\'entrée du magasin ?',
                showCancelButton: true, confirmButtonText: 'Imprimer', cancelButtonText: 'Plus tard',
            }).then((r) => { if (r.isConfirmed) window.open(@json(route('entrees.pdf', session('print'))), '_blank'); }), 2600));
        @endif
    </script>
    @include('partials.confirm-delete')
@endpush
