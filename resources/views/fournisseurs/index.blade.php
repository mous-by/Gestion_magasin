@extends('layouts.admin')

@section('title', 'Fournisseurs')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Fournisseurs</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Fournisseurs</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @php
        $activeCount = $fournisseurs->where('is_active', true)->count();
    @endphp
    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-store me-2'></i>FOURNISSEURS</h6>
            @can('create', \App\Models\Fournisseur::class)
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#create-fournisseur">
                    <i class='bx bx-plus'></i> Ajouter
                </button>
            @endcan
        </div>
        <div class="card-body">
            <div class="nv-chips mb-3" id="fournisseurs-filter">
                <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $fournisseurs->count() }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-actif">Actifs <b>{{ $activeCount }}</b></button>
                <button type="button" class="nv-chip" data-token="statut-inactif">Inactifs <b>{{ $fournisseurs->count() - $activeCount }}</b></button>
            </div>
            <div class="table-responsive">
                <table class="table" id="fournisseurs-table">
                    <thead>
                        <tr>
                            <th>FOURNISSEUR</th>
                            <th>CONTACT</th>
                            <th>ADRESSE</th>
                            <th>STATUT</th>
                            <th width="12%">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fournisseurs as $fournisseur)
                            <tr>
                                <td class="cell-wrap text-break">
                                    <div class="fw-semibold">{{ $fournisseur->name }}</div>
                                    <div class="small text-muted">{{ $fournisseur->admin_references ?? '' }}</div>
                                </td>
                                <td class="cell-wrap text-break">
                                    <div>{{ $fournisseur->contact_person ?? '—' }}</div>
                                    <div class="small text-muted">{{ collect([$fournisseur->phone, $fournisseur->email])->filter()->implode(' · ') }}</div>
                                </td>
                                <td class="cell-wrap text-break">{{ $fournisseur->address ?? '—' }}</td>
                                <td>
                                    <span class="d-none">{{ $fournisseur->is_active ? 'statut-actif' : 'statut-inactif' }}</span>
                                    @if ($fournisseur->is_active)
                                        <span class="badge bg-success-subtle">Actif</span>
                                    @else
                                        <span class="badge bg-secondary-subtle">Inactif</span>
                                    @endif
                                </td>
                                <td class="d-flex gap-1">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ficheFournisseur-{{ $fournisseur->id }}" title="Fiche du fournisseur">
                                        <i class='bx bx-show'></i>
                                    </button>
                                    @can('update', $fournisseur)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit-fournisseur-{{ $fournisseur->id }}" title="Modifier">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                    @endcan
                                    @can('delete', $fournisseur)
                                        <form method="POST" action="{{ route('fournisseurs.destroy', $fournisseur) }}" class="js-confirm-delete" data-title="Supprimer ce fournisseur ?" data-name="{{ $fournisseur->name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-primary btn-sm" title="Supprimer">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($fournisseurs as $fournisseur)
        {{-- Fiche (consultation) avec l'historique des livraisons (cahier §10) : entrées validées ou annulées. --}}
        <div class="modal fade" id="ficheFournisseur-{{ $fournisseur->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class='bx bx-store me-2'></i>{{ $fournisseur->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="nv-panel h-100">
                                    <div class="nv-panel-title">Coordonnées</div>
                                    <dl class="nv-dl">
                                        <dt>Adresse</dt><dd>{{ $fournisseur->address ?? '—' }}</dd>
                                        <dt>Téléphone</dt><dd>{{ $fournisseur->phone ?? '—' }}</dd>
                                        <dt>E-mail</dt><dd>{{ $fournisseur->email ?? '—' }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="nv-panel h-100">
                                    <div class="nv-panel-title">Suivi</div>
                                    <dl class="nv-dl">
                                        <dt>Personne contact</dt><dd>{{ $fournisseur->contact_person ?? '—' }}</dd>
                                        <dt>Réf. administratives</dt><dd>{{ $fournisseur->admin_references ?? '—' }}</dd>
                                        <dt>Statut</dt><dd>{{ $fournisseur->is_active ? 'Actif' : 'Inactif' }}</dd>
                                        <dt>Créé le</dt><dd>{{ $fournisseur->created_at->format('d/m/Y') }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="nv-panel-title mt-4">Historique des livraisons ({{ $fournisseur->entrees->count() }})</div>
                        @forelse ($fournisseur->entrees->take(10) as $entree)
                            <div class="nv-panel mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-bold">{{ $entree->number }}</div>
                                    <div class="small text-muted">{{ $entree->received_on->format('d/m/Y') }} · {{ $entree->details->count() }} article(s){{ $entree->delivery_note_number ? ' · bordereau '.$entree->delivery_note_number : '' }}</div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($entree->hasDiscrepancy())
                                        <span class="badge bg-warning-subtle">Écart</span>
                                    @endif
                                    <span class="badge {{ $entree->status->badgeClass() }}">{{ $entree->status->label() }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-secondary mb-0 py-2">Aucune livraison enregistrée.</div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @php
        $forms = collect([null])->merge($fournisseurs->filter(fn ($f) => auth()->user()->can('update', $f)));
    @endphp
    @foreach ($forms as $fournisseur)
        @continue($fournisseur === null && ! auth()->user()->can('create', \App\Models\Fournisseur::class))
        @php
            $modalId = $fournisseur ? "edit-fournisseur-{$fournisseur->id}" : 'create-fournisseur';
            $failed = $errors->any() && ($fournisseur ? old('fournisseur_id') == $fournisseur->id : ! old('fournisseur_id'));
            $sfx = $fournisseur ? '-'.$fournisseur->id : '';
            $val = fn (string $field) => $failed ? old($field) : $fournisseur?->{$field};
            $err = fn (string $field) => $failed && $errors->has($field);
        @endphp
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ $fournisseur ? route('fournisseurs.update', $fournisseur) : route('fournisseurs.store') }}">
                        @csrf
                        @if ($fournisseur)
                            @method('PUT')
                            <input type="hidden" name="fournisseur_id" value="{{ $fournisseur->id }}">
                        @endif
                        <div class="modal-header">
                            <h5 class="modal-title"><i class='bx bx-store me-2'></i>{{ $fournisseur ? 'Modifier — '.$fournisseur->name : 'Nouveau fournisseur' }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                @foreach ([
                                    ['name', 'Nom / raison sociale', true, 150, 'col-12'],
                                    ['address', 'Adresse', false, 255, 'col-12'],
                                    ['phone', 'Téléphone', false, 30, 'col-md-6'],
                                    ['email', 'E-mail', false, 150, 'col-md-6'],
                                    ['contact_person', 'Personne contact', false, 150, 'col-md-6'],
                                    ['admin_references', 'Références administratives (NIF, RCCM…)', false, 255, 'col-md-6'],
                                ] as [$name, $label, $required, $max, $col])
                                    <div class="{{ $col }}">
                                        <label for="{{ $name.$sfx }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
                                        <input type="{{ $name === 'email' ? 'email' : 'text' }}" class="form-control @if ($err($name)) is-invalid @endif" id="{{ $name.$sfx }}" name="{{ $name }}" value="{{ $val($name) }}" maxlength="{{ $max }}" @required($required)>
                                        @if ($err($name))<div class="invalid-feedback">{{ $errors->first($name) }}</div>@endif
                                    </div>
                                @endforeach
                                @if ($fournisseur)
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="active{{ $sfx }}" name="is_active" value="1" @checked($failed ? old('is_active') : $fournisseur->is_active)>
                                            <label class="form-check-label" for="active{{ $sfx }}">Fournisseur actif</label>
                                        </div>
                                        <small class="text-muted">Un fournisseur inactif reste dans l'historique mais n'est plus proposé pour une nouvelle entrée.</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if ($failed)
            <script>
                window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('{{ $modalId }}')).show());
            </script>
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>
        const fournisseursTable = $('#fournisseurs-table').DataTable({ scrollX: false });

        document.querySelectorAll('#fournisseurs-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#fournisseurs-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                fournisseursTable.column(3).search(chip.dataset.token || '', false, true).draw();
            });
        });
    </script>
    @include('partials.confirm-delete')
@endpush
