<?php

namespace App\Services\Entrees;

use App\Enums\DocumentStatus;
use App\Enums\MouvementType;
use App\Models\Entree;
use App\Models\EntreeDetail;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Documents\DocumentNumberService;
use App\Services\Stock\StockMovementService;
use App\Support\DocumentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Entrées / réceptions (cahier §11, §12, §25 ; CLAUDE.md D-M1) : seule porte d'écriture des entrées.
 * Brouillon : modifiable, sans effet sur le stock. Validation : numéro ENT-AAAA-NNNN, + quantité acceptée en stock
 * (RG02), audit. Annulation : contre-mouvements, motif obligatoire (RG05, RG11).
 */
class EntreeService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StockMovementService $stock,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Crée ou remplace un brouillon (en-tête et lignes). Les données viennent d'EntreeRequest (déjà validées).
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(User $user, array $data, ?Entree $entree = null): Entree
    {
        if ($entree !== null && ! $entree->isDraft()) {
            throw ValidationException::withMessages(['entree' => 'Une entrée validée ou annulée ne se modifie plus.']);
        }

        return DB::transaction(function () use ($user, $data, $entree) {
            $header = [
                'received_on' => $data['received_on'],
                'fournisseur_id' => $data['fournisseur_id'],
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'order_reference' => $data['order_reference'] ?? null,
                'observation' => $data['observation'] ?? null,
            ];

            if ($entree === null) {
                $entree = Entree::create($header + ['created_by' => $user->id]);
            } else {
                $entree->update($header);
            }

            // Lignes remplacées en bloc (un brouillon n'a encore aucun effet sur le stock) : une seule ligne d'audit
            // résume les lignes enregistrées, plutôt qu'une par ligne supprimée et recréée.
            EntreeDetail::withoutActivityLog(function () use ($entree, $data) {
                $entree->details()->get()->each->delete();

                foreach ($data['lines'] as $line) {
                    $entree->details()->create([
                        'article_id' => $line['article_id'],
                        'expected_qty' => $line['expected_qty'] ?? null,
                        'delivered_qty' => $line['delivered_qty'],
                        'accepted_qty' => $line['accepted_qty'],
                        'rejected_qty' => round((float) $line['delivered_qty'] - (float) $line['accepted_qty'], 3),
                        'rejection_reason' => $line['rejection_reason'] ?? null,
                        'observation' => $line['observation'] ?? null,
                    ]);
                }
            });

            $this->logger->log('entrees.draft_saved', 'entrees', "Brouillon d'entrée enregistré — {$entree->displayNumber()}", $entree, new: [
                'lignes' => collect($data['lines'])->map(fn ($line) => [
                    'article_id' => (int) $line['article_id'], 'livree' => $line['delivered_qty'], 'acceptee' => $line['accepted_qty'],
                ])->all(),
            ], actor: $user);

            return $entree->fresh('details');
        });
    }

    /**
     * Valide un brouillon : numéro, mouvements de stock (quantités acceptées seulement), audit.
     */
    public function validate(User $user, Entree $entree): Entree
    {
        return DB::transaction(function () use ($user, $entree) {
            $entree = Entree::whereKey($entree->getKey())->lockForUpdate()->firstOrFail();

            if (! $entree->isDraft()) {
                throw ValidationException::withMessages(['entree' => 'Cette entrée a déjà été validée ou annulée.']);
            }

            $entree->load('details.article');
            if ($entree->details->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Une entrée sans article ne peut pas être validée.']);
            }

            // RG07 : un article désactivé depuis la saisie du brouillon bloque la validation.
            $inactive = $entree->details->filter(fn ($detail) => ! $detail->article?->is_active || $detail->article->trashed());
            if ($inactive->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Article inactif ou supprimé : '.$inactive->map(fn ($d) => $d->article?->reference)->implode(', ').'. Modifiez le brouillon.']);
            }

            $number = $this->numbers->next(app(DocumentSettings::class)->prefix('entree'), (int) $entree->received_on->format('Y'));

            foreach ($entree->details as $detail) {
                if ((float) $detail->accepted_qty > 0) {
                    $this->stock->record($detail->article_id, $detail->accepted_qty, MouvementType::Entree, $entree, $number, $user, $entree->received_on->toDateString());
                }
            }

            Entree::withoutActivityLog(fn () => $entree->forceFill([
                'number' => $number,
                'has_discrepancy' => $entree->hasDiscrepancy(),
                'status' => DocumentStatus::Validee,
                'validated_at' => now(),
                'validated_by' => $user->id,
            ])->save());

            $this->logger->log('entrees.validated', 'entrees', "Entrée validée — {$number}", $entree, new: [
                'number' => $number,
                'fournisseur' => $entree->fournisseur?->name,
                'lignes' => $entree->details->count(),
                'ecarts' => $entree->hasDiscrepancy(),
            ], actor: $user);

            return $entree;
        });
    }

    /**
     * Annule une entrée validée par contre-mouvements (RG05). Refusée si une partie du stock reçu est déjà sortie
     * (le stock deviendrait négatif) : il faut alors une régularisation (phase 6).
     */
    public function cancel(User $user, Entree $entree, string $reason): Entree
    {
        return DB::transaction(function () use ($user, $entree, $reason) {
            $entree = Entree::whereKey($entree->getKey())->lockForUpdate()->firstOrFail();

            if (! $entree->isValidated()) {
                throw ValidationException::withMessages(['entree' => 'Seule une entrée validée peut être annulée.']);
            }

            foreach ($entree->details()->get() as $detail) {
                if ((float) $detail->accepted_qty > 0) {
                    $this->stock->record($detail->article_id, -1 * (float) $detail->accepted_qty, MouvementType::Annulation, $entree, $entree->number, $user, now()->toDateString(), $reason);
                }
            }

            Entree::withoutActivityLog(fn () => $entree->forceFill([
                'status' => DocumentStatus::Annulee,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ])->save());

            $this->logger->log('entrees.cancelled', 'entrees', "Entrée annulée — {$entree->number}", $entree, new: ['motif' => $reason], actor: $user);

            return $entree;
        });
    }

    /**
     * Enregistre la suite donnée à l'écart d'une entrée validée (cahier §15.1, RG08) : complément livré, avoir,
     * retour au fournisseur… L'écart sort alors des alertes. Ne touche pas au stock (une livraison complémentaire est
     * une nouvelle entrée).
     */
    public function resolveDiscrepancy(User $user, Entree $entree, string $resolution): Entree
    {
        return DB::transaction(function () use ($user, $entree, $resolution) {
            $entree = Entree::whereKey($entree->getKey())->lockForUpdate()->firstOrFail();

            if (! $entree->hasPendingDiscrepancy()) {
                throw ValidationException::withMessages(['entree' => 'Cette entrée n\'a pas d\'écart en attente de traitement.']);
            }

            Entree::withoutActivityLog(fn () => $entree->forceFill([
                'discrepancy_resolution' => $resolution,
                'discrepancy_resolved_at' => now(),
                'discrepancy_resolved_by' => $user->id,
            ])->save());

            $this->logger->log('entrees.discrepancy_resolved', 'entrees', "Écart traité — {$entree->number}", $entree, new: ['suite' => $resolution], actor: $user);

            return $entree;
        });
    }

    /**
     * Supprime un brouillon (soft delete, audité). Une entrée validée ne se supprime jamais (RG05).
     */
    public function deleteDraft(Entree $entree): void
    {
        if (! $entree->isDraft()) {
            throw ValidationException::withMessages(['entree' => 'Une entrée validée ne se supprime pas : annulez-la.']);
        }

        $entree->delete();
    }
}
