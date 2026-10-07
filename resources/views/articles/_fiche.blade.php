{{-- Fiche d'un article (consultation en modale, CLAUDE.md §7). La fiche de mouvement complète (cahier §15)
     viendra en phase 6. --}}
<div class="modal fade" id="ficheArticle-{{ $article->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class='bx bx-package me-2'></i>{{ $article->label() }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Identification</div>
                            <dl class="nv-dl">
                                <dt>Référence</dt><dd>{{ $article->reference }}</dd>
                                <dt>Désignation</dt><dd>{{ $article->designation }}</dd>
                                <dt>Catégorie</dt><dd>{{ $article->category?->name ?? '—' }}</dd>
                                <dt>Unité</dt><dd>{{ $article->unite ? $article->unite->name.' ('.$article->unite->symbol.')' : '—' }}</dd>
                                <dt>Marque / modèle</dt><dd>{{ $article->brand_model ?? '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Gestion</div>
                            <dl class="nv-dl">
                                <dt>Stock</dt><dd><strong>{{ $article->stockForDisplay() }} {{ $article->unite?->symbol }}</strong></dd>
                                <dt>Seuil minimum</dt><dd>{{ $article->thresholdForDisplay() ?? '—' }}</dd>
                                <dt>Emplacement</dt><dd>{{ $article->location ?? '—' }}</dd>
                                <dt>Statut</dt><dd>{{ $article->is_active ? 'Actif' : 'Inactif' }}</dd>
                                <dt>Créé le</dt><dd>{{ $article->created_at->format('d/m/Y') }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                @if ($article->observation)
                    <div class="nv-panel mt-3">
                        <div class="nv-panel-title">Observation</div>
                        <p class="mb-0 text-break">{{ $article->observation }}</p>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
