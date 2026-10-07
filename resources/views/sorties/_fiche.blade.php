{{-- Détail d'une sortie (consultation en modale) : demande, lignes avec le stock disponible figé à la validation, traçabilité. --}}
<div class="modal fade" id="ficheSortie-{{ $sortie->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class='bx bx-log-out-circle me-2'></i>{{ $sortie->displayNumber() }} — {{ $sortie->service?->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Demande</div>
                            <dl class="nv-dl">
                                <dt>Date</dt><dd>{{ $sortie->issued_on->format('d/m/Y') }}</dd>
                                <dt>Service</dt><dd>{{ $sortie->service?->name ?? '—' }}</dd>
                                <dt>Demandeur</dt><dd>{{ $sortie->requester_name }}</dd>
                                <dt>Motif</dt><dd class="text-break">{{ $sortie->reason }}</dd>
                                <dt>Réf. papier</dt><dd>{{ $sortie->paper_reference ?? '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Traçabilité</div>
                            <dl class="nv-dl">
                                <dt>Statut</dt><dd><span class="badge {{ $sortie->status->badgeClass() }}">{{ $sortie->status->label() }}</span></dd>
                                <dt>Saisie par</dt><dd>{{ $sortie->creator?->displayName() ?? '—' }}, le {{ $sortie->created_at->format('d/m/Y H:i') }}</dd>
                                @if ($sortie->validated_at)
                                    <dt>Validée par</dt><dd>{{ $sortie->validator?->displayName() ?? '—' }}, le {{ $sortie->validated_at->format('d/m/Y H:i') }}</dd>
                                @endif
                                @if ($sortie->cancelled_at)
                                    <dt>Annulée par</dt><dd>{{ $sortie->canceller?->displayName() ?? '—' }}, le {{ $sortie->cancelled_at->format('d/m/Y H:i') }}</dd>
                                    <dt>Motif</dt><dd class="text-break">{{ $sortie->cancel_reason }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>ARTICLE</th><th>UNITÉ</th><th>STOCK AVANT</th><th>SORTIE</th><th>STOCK APRÈS</th><th>OBSERVATION</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($sortie->details as $detail)
                                <tr>
                                    <td class="cell-wrap text-break"><div class="fw-semibold">{{ $detail->article?->designation }}</div><div class="small text-muted">{{ $detail->article?->reference }}</div></td>
                                    <td>{{ $detail->article?->unite?->symbol }}</td>
                                    <td>{{ $detail->stock_before !== null ? \App\Support\Quantity::format($detail->stock_before) : '—' }}</td>
                                    <td class="fw-semibold">{{ \App\Support\Quantity::format($detail->quantity) }}</td>
                                    <td>{{ $detail->stock_before !== null ? \App\Support\Quantity::format((float) $detail->stock_before - (float) $detail->quantity) : '—' }}</td>
                                    <td class="cell-wrap text-break small">{{ $detail->observation ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($sortie->observation)
                    <div class="nv-panel mt-2">
                        <div class="nv-panel-title">Observation</div>
                        <p class="mb-0 text-break">{{ $sortie->observation }}</p>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <a href="{{ route('sorties.pdf', $sortie) }}" target="_blank" class="btn btn-outline-primary"><i class='bx bx-printer me-1'></i>Bon de sortie</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
