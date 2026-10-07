@extends('layouts.admin')

@section('title', 'Sorties')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Sorties</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sorties de matériel</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @php
        $count = fn (\App\Enums\DocumentStatus $status) => $sorties->where('status', $status)->count();
    @endphp
    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-log-out-circle me-2'></i>SORTIES DE MATÉRIEL</h6>
            @can('create', \App\Models\Sortie::class)
                <a href="{{ route('sorties.create') }}" class="btn btn-light btn-sm d-flex align-items-center gap-1">
                    <i class='bx bx-plus'></i> Nouvelle sortie
                </a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('sorties.index') }}" class="row g-2 align-items-end mb-3">
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
                        <a href="{{ route('sorties.index') }}" class="btn btn-outline-secondary btn-sm">Toute la période</a>
                    @endif
                </div>
            </form>

            <div class="nv-chips mb-3" id="sorties-filter">
                <button type="button" class="nv-chip active" data-token="">Toutes <b>{{ $sorties->count() }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-brouillon">Brouillons <b>{{ $count(\App\Enums\DocumentStatus::Brouillon) }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-validee">Validées <b>{{ $count(\App\Enums\DocumentStatus::Validee) }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-annulee">Annulées <b>{{ $count(\App\Enums\DocumentStatus::Annulee) }}</b></button>
            </div>
            <div class="table-responsive">
                <table class="table" id="sorties-table">
                    <thead>
                        <tr>
                            <th>SORTIE</th>
                            <th>SERVICE / DEMANDEUR</th>
                            <th>ARTICLES</th>
                            <th>STATUT</th>
                            <th width="16%">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sorties as $sortie)
                            <tr>
                                <td data-order="{{ $sortie->issued_on->format('Ymd') }}{{ str_pad($sortie->id, 8, '0', STR_PAD_LEFT) }}">
                                    <div class="fw-semibold">{{ $sortie->displayNumber() }}</div>
                                    <div class="small text-muted">{{ $sortie->issued_on->format('d/m/Y') }}</div>
                                </td>
                                <td class="cell-wrap text-break">
                                    <div>{{ $sortie->service?->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ $sortie->requester_name }} · {{ $sortie->reason }}</div>
                                </td>
                                <td class="cell-wrap">
                                    <div>{{ $sortie->details->count() }} article(s)</div>
                                    <div class="small text-muted text-break">{{ $sortie->details->take(3)->map(fn ($d) => $d->article?->designation)->implode(', ') }}{{ $sortie->details->count() > 3 ? '…' : '' }}</div>
                                </td>
                                <td>
                                    <span class="d-none">statut-{{ $sortie->status->value }}</span>
                                    <span class="badge {{ $sortie->status->badgeClass() }}">{{ $sortie->status->label() }}</span>
                                </td>
                                <td class="d-flex gap-1 flex-wrap">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ficheSortie-{{ $sortie->id }}" title="Détail de la sortie">
                                        <i class='bx bx-show'></i>
                                    </button>
                                    @if ($sortie->isDraft())
                                        @can('update', $sortie)
                                            <a href="{{ route('sorties.edit', $sortie) }}" class="btn btn-primary btn-sm" title="Modifier le brouillon"><i class='bx bx-edit'></i></a>
                                        @endcan
                                        @can('validate', $sortie)
                                            <form method="POST" action="{{ route('sorties.validate', $sortie) }}" class="js-confirm-validate" data-name="{{ $sortie->displayNumber() }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm" title="Valider (diminution du stock)"><i class='bx bx-check-shield'></i></button>
                                            </form>
                                        @endcan
                                        @can('delete', $sortie)
                                            <form method="POST" action="{{ route('sorties.destroy', $sortie) }}" class="js-confirm-delete" data-title="Supprimer ce brouillon ?" data-name="{{ $sortie->displayNumber() }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-primary btn-sm" title="Supprimer le brouillon"><i class='bx bx-trash'></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                    <a href="{{ route('sorties.pdf', $sortie) }}" target="_blank" class="btn btn-primary btn-sm" title="Bon de sortie (PDF)"><i class='bx bx-printer'></i></a>
                                    @if ($sortie->isValidated())
                                        @can('cancel', $sortie)
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cancelSortie-{{ $sortie->id }}" title="Annuler la sortie"><i class='bx bx-undo'></i></button>
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

    @foreach ($sorties as $sortie)
        @include('sorties._fiche', ['sortie' => $sortie])

        @if ($sortie->isValidated())
            @can('cancel', $sortie)
                @php
                    $cancelFailed = $errors->has('reason') && (int) old('sortie_id') === $sortie->id;
                @endphp
                <div class="modal fade" id="cancelSortie-{{ $sortie->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('sorties.cancel', $sortie) }}">
                                @csrf
                                <input type="hidden" name="sortie_id" value="{{ $sortie->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class='bx bx-undo me-2'></i>Annuler la sortie {{ $sortie->number }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <p class="small">Les quantités sorties reviennent en stock par un mouvement d'annulation. La sortie reste dans l'historique, marquée « Annulée ».</p>
                                    <label for="reason-{{ $sortie->id }}" class="form-label fw-semibold">Motif de l'annulation <span class="text-danger">*</span></label>
                                    <textarea class="form-control @if ($cancelFailed) is-invalid @endif" id="reason-{{ $sortie->id }}" name="reason" rows="3" maxlength="255" required>{{ $cancelFailed ? old('reason') : '' }}</textarea>
                                    @if ($cancelFailed)<div class="invalid-feedback">{{ $errors->first('reason') }}</div>@endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                    <button type="submit" class="btn btn-danger">Annuler la sortie</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @if ($cancelFailed)
                    <script>
                        window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('cancelSortie-{{ $sortie->id }}')).show());
                    </script>
                @endif
            @endcan
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>
        const sortiesTable = $('#sorties-table').DataTable({ scrollX: false, order: [[0, 'desc']] });

        document.querySelectorAll('#sorties-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#sorties-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                sortiesTable.column(3).search(chip.dataset.token || '', false, true).draw();
            });
        });

        $(document).on('submit', '.js-confirm-validate', function (event) {
            event.preventDefault();
            const form = this;
            Swal.fire({
                icon: 'question',
                title: 'Valider ' + $(form).data('name') + ' ?',
                text: 'Les quantités seront retirées du stock (refus si le stock est insuffisant) ; la sortie ne sera plus modifiable.',
                showCancelButton: true, confirmButtonText: 'Valider', cancelButtonText: 'Revenir',
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });

        @if (session('print'))
            // Après validation : proposer l'impression du bon de sortie (cahier §14).
            window.addEventListener('DOMContentLoaded', () => setTimeout(() => Swal.fire({
                icon: 'success', title: 'Sortie validée', text: 'Imprimer le bon de sortie ?',
                showCancelButton: true, confirmButtonText: 'Imprimer', cancelButtonText: 'Plus tard',
            }).then((r) => { if (r.isConfirmed) window.open(@json(route('sorties.pdf', session('print'))), '_blank'); }), 2600));
        @endif
    </script>
    @include('partials.confirm-delete')
@endpush
