{{-- Fiche d'un utilisateur (consultation en modale, §7) : compte, rattachement et état. Aucun secret affiché. --}}
<div class="modal fade" id="ficheUser-{{ $user->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class='bx bx-user me-2'></i>{{ $user->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-primary-subtle">{{ $user->roleName()?->label() ?? 'Sans rôle' }}</span>
                    @if ($user->is_active)
                        <span class="badge bg-success-subtle">Actif</span>
                    @else
                        <span class="badge bg-secondary-subtle">Inactif</span>
                    @endif
                    @if ($user->must_change_password)
                        <span class="badge bg-warning-subtle">Mot de passe temporaire</span>
                    @endif
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Compte</div>
                            <dl class="nv-dl">
                                <dt>Nom</dt><dd>{{ $user->name }}</dd>
                                <dt>Téléphone</dt><dd>{{ $user->phone }}</dd>
                                <dt>Rôle</dt><dd>{{ $user->roleName()?->label() ?? '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="nv-panel h-100">
                            <div class="nv-panel-title">Rattachement et activité</div>
                            <dl class="nv-dl">
                                <dt>Dernière connexion</dt><dd>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</dd>
                                <dt>Créé le</dt><dd>{{ $user->created_at->format('d/m/Y') }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
