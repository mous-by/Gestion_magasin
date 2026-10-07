<?php

namespace App\Services\Sorties;

use App\Enums\DocumentStatus;
use App\Enums\MouvementType;
use App\Models\Sortie;
use App\Models\SortieDetail;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Documents\DocumentNumberService;
use App\Services\Stock\StockMovementService;
use App\Support\DocumentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sorties (cahier §13, §14, §25 ; CLAUDE.md D-M1, D-M2) : seule porte d'écriture des sorties.
 * Brouillon : sans effet sur le stock. Validation : numéro SOR-AAAA-NNNN, − quantité en stock (RG03), refusée en
 * entier si une ligne dépasse le stock disponible au moment même de la validation (RG04, verrou par article).
 * Annulation : contre-mouvement qui remet le stock, motif obligatoire (RG05, RG11).
 */
class SortieService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StockMovementService $stock,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data  données validées par SortieRequest
     */
    public function saveDraft(User $user, array $data, ?Sortie $sortie = null): Sortie
    {
        if ($sortie !== null && ! $sortie->isDraft()) {
            throw ValidationException::withMessages(['sortie' => 'Une sortie validée ou annulée ne se modifie plus.']);
        }

        return DB::transaction(function () use ($user, $data, $sortie) {
            $header = [
                'issued_on' => $data['issued_on'],
                'requester_name' => $data['requester_name'],
                'service_id' => $data['service_id'],
                'reason' => $data['reason'],
                'paper_reference' => $data['paper_reference'] ?? null,
                'observation' => $data['observation'] ?? null,
            ];

            if ($sortie === null) {
                $sortie = Sortie::create($header + ['created_by' => $user->id]);
            } else {
                $sortie->update($header);
            }

            // Lignes remplacées en bloc, une seule ligne d'audit (comme les entrées).
            SortieDetail::withoutActivityLog(function () use ($sortie, $data) {
                $sortie->details()->get()->each->delete();

                foreach ($data['lines'] as $line) {
                    $sortie->details()->create([
                        'article_id' => $line['article_id'],
                        'quantity' => $line['quantity'],
                        'observation' => $line['observation'] ?? null,
                    ]);
                }
            });

            $this->logger->log('sorties.draft_saved', 'sorties', "Brouillon de sortie enregistré — {$sortie->displayNumber()}", $sortie, new: [
                'lignes' => collect($data['lines'])->map(fn ($line) => ['article_id' => (int) $line['article_id'], 'quantite' => $line['quantity']])->all(),
            ], actor: $user);

            return $sortie->fresh('details');
        });
    }

    public function validate(User $user, Sortie $sortie): Sortie
    {
        return DB::transaction(function () use ($user, $sortie) {
            $sortie = Sortie::whereKey($sortie->getKey())->lockForUpdate()->firstOrFail();

            if (! $sortie->isDraft()) {
                throw ValidationException::withMessages(['sortie' => 'Cette sortie a déjà été validée ou annulée.']);
            }

            $sortie->load('details.article', 'service');
            if ($sortie->details->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Une sortie sans article ne peut pas être validée.']);
            }

            $inactive = $sortie->details->filter(fn ($detail) => ! $detail->article?->is_active || $detail->article->trashed());
            if ($inactive->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Article inactif ou supprimé : '.$inactive->map(fn ($d) => $d->article?->reference)->implode(', ').'. Modifiez le brouillon.']);
            }

            $number = $this->numbers->next(app(DocumentSettings::class)->prefix('sortie'), (int) $sortie->issued_on->format('Y'));

            // StockMovementService refuse tout solde négatif : la transaction entière (numéro compris) est annulée.
            foreach ($sortie->details as $detail) {
                $mouvement = $this->stock->record($detail->article_id, -1 * (float) $detail->quantity, MouvementType::Sortie, $sortie, $number, $user, $sortie->issued_on->toDateString());

                SortieDetail::withoutActivityLog(fn () => $detail->forceFill([
                    'stock_before' => round((float) $mouvement->balance_after + (float) $detail->quantity, 3),
                ])->save());
            }

            Sortie::withoutActivityLog(fn () => $sortie->forceFill([
                'number' => $number,
                'status' => DocumentStatus::Validee,
                'validated_at' => now(),
                'validated_by' => $user->id,
            ])->save());

            $this->logger->log('sorties.validated', 'sorties', "Sortie validée — {$number}", $sortie, new: [
                'number' => $number,
                'service' => $sortie->service?->name,
                'demandeur' => $sortie->requester_name,
                'lignes' => $sortie->details->count(),
            ], actor: $user);

            return $sortie;
        });
    }

    public function cancel(User $user, Sortie $sortie, string $reason): Sortie
    {
        return DB::transaction(function () use ($user, $sortie, $reason) {
            $sortie = Sortie::whereKey($sortie->getKey())->lockForUpdate()->firstOrFail();

            if (! $sortie->isValidated()) {
                throw ValidationException::withMessages(['sortie' => 'Seule une sortie validée peut être annulée.']);
            }

            foreach ($sortie->details()->get() as $detail) {
                $this->stock->record($detail->article_id, $detail->quantity, MouvementType::Annulation, $sortie, $sortie->number, $user, now()->toDateString(), $reason);
            }

            Sortie::withoutActivityLog(fn () => $sortie->forceFill([
                'status' => DocumentStatus::Annulee,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ])->save());

            $this->logger->log('sorties.cancelled', 'sorties', "Sortie annulée — {$sortie->number}", $sortie, new: ['motif' => $reason], actor: $user);

            return $sortie;
        });
    }

    public function deleteDraft(Sortie $sortie): void
    {
        if (! $sortie->isDraft()) {
            throw ValidationException::withMessages(['sortie' => 'Une sortie validée ne se supprime pas : annulez-la.']);
        }

        $sortie->delete();
    }
}
