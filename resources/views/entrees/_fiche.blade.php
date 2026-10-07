{{-- Détail d'une entrée (consultation en modale) : en-tête, lignes avec écarts (cahier §12), suivi de l'écart (§15.1) et traçabilité (§16). --}}
@php
    $planned = $entree->isPlanned();
@endphp
<div class="modal fade" id="ficheEntree-{{ $entree->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class='bx bx-log-in-circle me-2'></i>{{ $entree->displayNumber() }} — {{ $entree->fournisseur?->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Livraison</div>
                            <dl class="nv-dl">
                                <dt>Date de réception</dt><dd>{{ $entree->received_on->format('d/m/Y') }}</dd>
                                <dt>Fournisseur</dt><dd>{{ $entree->fournisseur?->name ?? '—' }}</dd>
                                <dt>N° bordereau</dt><dd>{{ $entree->delivery_note_number ?? '—' }}</dd>
                                <dt>Réf. commande</dt><dd>{{ $entree->order_reference ?? '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Traçabilité</div>
                            <dl class="nv-dl">
                                <dt>Statut</dt><dd><span class="badge {{ $entree->status->badgeClass() }}">{{ $entree->status->label() }}</span></dd>
                                <dt>Saisie par</dt><dd>{{ $entree->creator?->displayName() ?? '—' }}, le {{ $entree->created_at->format('d/m/Y H:i') }}</dd>
                                @if ($entree->validated_at)
                                    <dt>Validée par</dt><dd>{{ $entree->validator?->displayName() ?? '—' }}, le {{ $entree->validated_at->format('d/m/Y H:i') }}</dd>
                                @endif
                                @if ($entree->cancelled_at)
                                    <dt>Annulée par</dt><dd>{{ $entree->canceller?->displayName() ?? '—' }}, le {{ $entree->cancelled_at->format('d/m/Y H:i') }}</dd>
                                    <dt>Motif</dt><dd class="text-break">{{ $entree->cancel_reason }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>ARTICLE</th><th>UNITÉ</th><th>PRÉVU</th><th>LIVRÉ</th><th>ACCEPTÉ</th><th>REJETÉ</th><th>ÉCARTS</th><th>OBSERVATION</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($entree->details as $detail)
                                @php
                                    $discrepancies = $detail->discrepancies($planned);
                                @endphp
                                <tr @class(['table-warning' => $discrepancies !== []])>
                                    <td class="cell-wrap text-break"><div class="fw-semibold">{{ $detail->article?->designation }}</div><div class="small text-muted">{{ $detail->article?->reference }}</div></td>
                                    <td>{{ $detail->article?->unite?->symbol }}</td>
                                    <td>{{ \App\Support\Quantity::format($detail->expected_qty) ?? '—' }}</td>
                                    <td>{{ \App\Support\Quantity::format($detail->delivered_qty) }}</td>
                                    <td class="fw-semibold">{{ \App\Support\Quantity::format($detail->accepted_qty) }}</td>
                                    <td>{{ (float) $detail->rejected_qty > 0 ? \App\Support\Quantity::format($detail->rejected_qty) : '—' }}</td>
                                    <td>
                                        @forelse ($discrepancies as $label)
                                            <span class="badge bg-warning-subtle">{{ $label }}</span>
                                        @empty
                                            <span class="text-muted small">—</span>
                                        @endforelse
                                    </td>
                                    <td class="cell-wrap text-break small">{{ $detail->observation ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($entree->has_discrepancy)
                    <div class="nv-panel mt-2 {{ $entree->discrepancy_resolved_at ? '' : 'border border-danger' }}">
                        <div class="nv-panel-title">Suivi de l'écart</div>
                        @if ($entree->discrepancy_resolved_at)
                            <p class="mb-1 text-break">{{ $entree->discrepancy_resolution }}</p>
                            <div class="small text-muted">Traité par {{ $entree->resolver?->displayName() ?? '—' }} le {{ $entree->discrepancy_resolved_at->format('d/m/Y H:i') }}</div>
                        @else
                            <p class="mb-0 text-danger">Écart non traité : la suite donnée (complément, avoir, retour…) reste à enregistrer.</p>
                        @endif
                    </div>
                @endif
                @if ($entree->observation)
                    <div class="nv-panel mt-2">
                        <div class="nv-panel-title">Observation générale</div>
                        <p class="mb-0 text-break">{{ $entree->observation }}</p>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <a href="{{ route('entrees.pdf', $entree) }}" target="_blank" class="btn btn-outline-primary"><i class='bx bx-printer me-1'></i>Fiche de réception</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
