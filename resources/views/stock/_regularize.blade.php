{{-- Modale de régularisation d'un article (RG11) : stock constaté, date, motif obligatoire. --}}
@php
    $failed = $errors->hasAny(['new_balance', 'reason', 'regularized_on']) && (int) old('article_id') === $article->id;
@endphp
<div class="modal fade" id="regularize-{{ $article->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.regularize', $article) }}">
                @csrf
                <input type="hidden" name="article_id" value="{{ $article->id }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-adjust me-2'></i>Régulariser — {{ $article->reference }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small mb-3">{{ $article->designation }} — stock calculé : <strong>{{ $article->stockForDisplay() }} {{ $article->unite?->symbol }}</strong>.
                        Saisissez le stock <strong>réellement constaté</strong> : l'écart sera enregistré comme un mouvement de régularisation numéroté, avec votre motif.</p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="new_balance-{{ $article->id }}" class="form-label fw-semibold">Stock constaté <span class="text-danger">*</span></label>
                            <input type="text" inputmode="decimal" class="form-control @if ($failed && $errors->has('new_balance')) is-invalid @endif" id="new_balance-{{ $article->id }}" name="new_balance" value="{{ $failed ? old('new_balance') : '' }}" required>
                            @if ($failed && $errors->has('new_balance'))<div class="invalid-feedback">{{ $errors->first('new_balance') }}</div>@endif
                        </div>
                        <div class="col-6">
                            <label for="regularized_on-{{ $article->id }}" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @if ($failed && $errors->has('regularized_on')) is-invalid @endif" id="regularized_on-{{ $article->id }}" name="regularized_on" value="{{ $failed ? old('regularized_on') : now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                            @if ($failed && $errors->has('regularized_on'))<div class="invalid-feedback">{{ $errors->first('regularized_on') }}</div>@endif
                        </div>
                        <div class="col-12">
                            <label for="reason-{{ $article->id }}" class="form-label fw-semibold">Motif <span class="text-danger">*</span></label>
                            <textarea class="form-control @if ($failed && $errors->has('reason')) is-invalid @endif" id="reason-{{ $article->id }}" name="reason" rows="2" maxlength="255" placeholder="Casse constatée lors du rangement, erreur de comptage…" required>{{ $failed ? old('reason') : '' }}</textarea>
                            @if ($failed && $errors->has('reason'))<div class="invalid-feedback">{{ $errors->first('reason') }}</div>@endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer la régularisation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if ($failed)
    <script>
        window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('regularize-{{ $article->id }}')).show());
    </script>
@endif
